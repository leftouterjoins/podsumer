<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \Exception;
use \JSON_THROW_ON_ERROR;
use \JSON_INVALID_UTF8_IGNORE;
use \JSON_OBJECT_AS_ARRAY;
use \PDO;
use \SimpleXMLElement;

class State
{
    use TStateSchemaMigrations;

    CONST VERSION = 6; # The version of the schema for this commit.

    protected Main $main;
    protected $state_file_path;
    protected $sql_dir_path;
    protected $pdo;

    function __construct(Main $main)
    {
        $this->main = $main;
        $state_file_path = $this->main->getStateFilePath();

        $state_dir = dirname($state_file_path);
        if (!is_dir($state_dir) && !mkdir($state_dir, 0755, true)) {
            throw new Exception("Cannot find or create the state directory: $state_dir");
        }

        $this->state_file_path = $state_file_path;
        $this->sql_dir_path = $this->main->getInstallPath()
            . $this->main->getConf('podsumer', 'sql_dir');

        $this->pdo = new PDO('sqlite:' . $this->state_file_path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->optimizeSettings();
        $this->checkDBInstall();
        $this->checkDBVersion();
    }

    protected function optimizeSettings()
    {
        $this->pdo->exec("PRAGMA journal_mode = WAL;");
        $this->pdo->exec("PRAGMA synchronous = NORMAL;"); // Changed from OFF to NORMAL for data safety
        $this->pdo->exec("PRAGMA cache_size = -20000;");
        $this->pdo->exec("PRAGMA foreign_keys = ON;");
        $this->pdo->exec("PRAGMA temp_store = MEMORY;");
        // Removed duplicate foreign_keys pragma
    }

    protected function installTables()
    {
        $table_sql = file_get_contents($this->sql_dir_path . '/tables.sql');
        $this->pdo->exec($table_sql);
    }

    protected function checkDBInstall()
    {
        // Does the db file exist?
        if (!file_exists($this->state_file_path)) {
            throw new Exception('No DB file found at path: ' . $this->state_file_path);
        }

        // Do the tables expected exist?
        $this->installTables();
    }

    protected function query(string $sql, array $params = []): array|bool
    {
        try {

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (Exception $e) {
            $this->main->log($e->getMessage());

            if ($this->main->getTestMode()) {
                throw $e;
            }


            return false;
        }
    }

    public function addFeed(Feed $feed): ?int
    {
        if (!$feed->feedLoaded()) {
            return 0;
        }

        $feed_url_hash = $feed->getUrlHash();

        $feed_rec = [];
        $feed_rec['url_hash'] = $feed_url_hash;
        $feed_rec['url'] = $feed->getUrl();
        $feed_rec['name'] = $feed->getTitle();
        $feed_rec['last_update'] = $feed->getLastUpdated()->format('c');
        $feed_rec['description'] = $feed->getDescription();
        $feed_rec['image'] = $this->cacheFile($feed->getImage(), $feed_rec);
        $feed_rec['image_url'] = $feed->getImage();

        $sql = 'INSERT INTO feeds (url_hash, name, last_update, url, description, image, image_url) VALUES (:url_hash, :name, :last_update, :url, :description, :image, :image_url) ON CONFLICT(url_hash) DO UPDATE SET id=id';
        $this->query($sql, $feed_rec);
        $feed_id = $this->pdo->lastInsertId();

        if ('0' !== $feed_id) {
            $feed->setFeedId(intval($feed_id));
        }

        $items = $feed->getFeedItems();
        $this->addFeedItems($items, $feed);

        return intval($feed_id);
    }

    protected function addFeedItems(\SimpleXMLElement $items, Feed $feed)
    {

        # Download custom image only for the first n episodes.

        $first_hundred = $this->main->getConf('podsumer', 'per_item_art_download') ?? 50;

        $feed_lookup = $this->getFeed($feed->getFeedId());

        foreach ($items as $item) {
            $new_item = new Item($this->main, $item, $feed);
            $item_rec = [
                'feed_id' => $feed->getFeedId(),
                'guid' => $new_item->getGuid(),
                'name' => $new_item->getName(),
                'published' => $new_item->getPublished()->format('c'),
                'description' => $new_item->getDescription(),
                'size' => $new_item->getSize(),
                'audio_url' => $new_item->getAudioFileUrl(),
                'image_url' => $new_item->getImage() ?: null,
                'image' => ($first_hundred > 0)
                    ? $this->cacheFile($new_item->getImage() ?: null, $feed_lookup)
                    : null
            ];

            $first_hundred--;

            $sql = 'INSERT INTO items (feed_id, guid, name, published, description, size, audio_url, image, image_url) VALUES (:feed_id, :guid, :name, :published, :description, :size, :audio_url, :image, :image_url) ON CONFLICT(guid) DO UPDATE SET name=:name, published=:published, description=:description, size=:size, audio_url=:audio_url, image=:image, image_url=:image_url';
            $this->query($sql, $item_rec);
        }
    }

    public function cacheFile(string|null $url, array $feed): int|null
    {
        $this->main->log("Fetching $url");

        if (!empty($url)) {
            $file = new File($this->main);
            return $file->cacheUrl($url, $feed);
        }

        return null;
    }

    public function getStateDirPath(): string
    {
        return dirname($this->state_file_path);
    }

    public function getFeeds(): array
    {
        $sql = 'SELECT id, name, last_update, url, description, image, image_url FROM feeds ORDER BY last_update DESC';
        return $this->query($sql);
     }

    public function getFeed(int $id): array
    {
        $sql = 'SELECT id, name, description, url, image, image_url, last_update, url_hash FROM feeds WHERE id = :id';
        return $this->query($sql, ['id' => $id])[0] ?? [];
    }

    public function getFeedForItem(int $item_id): array
    {
        $sql = 'SELECT feeds.id, feeds.name, feeds.description, feeds.url, feeds.image, feeds.image_url, feeds.last_update, feeds.url_hash FROM feeds JOIN items ON feeds.id = items.feed_id WHERE items.id = :id';
        return $this->query($sql, ['id' => $item_id])[0] ?? [];
    }

    public function getFeedItem(int $item_id): array
    {
        $sql = 'SELECT items.name, items.feed_id, items.id, items.guid, items.audio_url, items.audio_file, COALESCE(items.image, feeds.image) AS image, items.size, items.published, items.description, items.playback_position, items.ad_sections, items.transcript FROM items JOIN feeds ON feeds.id = items.feed_id WHERE items.id = :id ORDER BY items.published DESC';
        $result = $this->query($sql, ['id' => $item_id]);
        return $result !== false && isset($result[0]) ? $result[0] : [];
    }

    public function getFeedItems(int $feed_id): array
    {
        $sql = 'SELECT items.name, items.feed_id, items.id, items.guid, items.audio_url, items.audio_file, COALESCE(items.image, feeds.image) AS image, items.size, items.published, items.description, items.playback_position, items.ad_sections FROM items JOIN feeds ON feeds.id = items.feed_id WHERE items.feed_id = :id ORDER BY items.published DESC';
        $result = $this->query($sql, ['id' => $feed_id]);

        // The query helper returns false when an exception is caught. Convert that
        // into an empty array so that we always satisfy the declared return type
        // and avoid triggering a TypeError further up the call-stack.
        return (false === $result) ? [] : $result;
    }

    public function getAllItems(): array
    {
        $sql = 'SELECT items.name, items.feed_id, items.id, items.guid, items.audio_url, items.audio_file, COALESCE(items.image, feeds.image) AS image, items.size, items.published, items.description, items.playback_position, items.ad_sections, feeds.name AS feed_name FROM items JOIN feeds ON feeds.id = items.feed_id ORDER BY items.published DESC';

        $result = $this->query($sql);

        return (false === $result) ? [] : $result;
     }

    public function getAllItemsPage(int $limit, int $page = 1): array
    {
        $offset = ($page - 1) * $limit;
        $sql = 'SELECT items.name, items.feed_id, items.id, items.guid, items.audio_url, items.audio_file, COALESCE(items.image, feeds.image) AS image, items.size, items.published, items.description, items.playback_position, items.ad_sections, feeds.name AS feed_name FROM items JOIN feeds ON feeds.id = items.feed_id ORDER BY items.published DESC LIMIT :limit OFFSET :offset';
        $params = ['limit' => $limit, 'offset' => $offset];
        $result = $this->query($sql, $params);

        return (false === $result) ? [] : $result;
    }

    public function countAllItems(): int
    {
        $sql = 'SELECT COUNT(*) AS count FROM items';
        $result = $this->query($sql);

        return intval($result[0]['count'] ?? 0);
    }

    public function getFeedItemsPage(int $feed_id, int $limit, int $page = 1): array
    {
        $offset = ($page - 1) * $limit;
        $sql = 'SELECT items.name, items.feed_id, items.id, items.guid, items.audio_url, items.audio_file, COALESCE(items.image, feeds.image) AS image, items.size, items.published, items.description, items.playback_position, items.ad_sections FROM items JOIN feeds ON feeds.id = items.feed_id WHERE items.feed_id = :id ORDER BY items.published DESC LIMIT :limit OFFSET :offset';
        $params = ['id' => $feed_id, 'limit' => $limit, 'offset' => $offset];
        $result = $this->query($sql, $params);


        return (false === $result) ? [] : $result;
    }

    public function countFeedItems(int $feed_id): int
    {
        $sql = 'SELECT COUNT(*) AS count FROM items WHERE feed_id = :id';
        $result = $this->query($sql, ['id' => $feed_id]);

        return intval($result[0]['count'] ?? 0);
    }

    public function getFeedByHash(string $hash): array
    {
        $sql = 'SELECT id, name, last_update, url, description FROM feeds WHERE url_hash = :hash';
        return $this->query($sql, ['hash' => $hash]);
    }

    public function getFileById(int $file_id): array
    {
        $sql = 'SELECT files.id, url, url_hash, mimetype, filename, size, cached, file_contents.content_hash, file_contents.data FROM files JOIN file_contents ON files.content_hash = file_contents.content_hash WHERE files.id = :file_id';
        $file = $this->query($sql, ['file_id' => $file_id])[0] ?? [];

        if (!empty($file)) {
            $filename = $file['data'];

            try {
                $file['data'] = $this->loadFile($filename);
            } catch (Exception $e) {

            }

            $file['filename'] = $filename;
        }

        return $file;
    }

    public function getFileByUrlHash(string $url_hash): array
    {
        $sql = 'SELECT files.id, url, url_hash, mimetype, filename, size, cached, file_contents.content_hash, file_contents.data FROM files JOIN file_contents ON files.content_hash = file_contents.content_hash WHERE url_hash = :url_hash';
        $file = $this->query($sql, ['url_hash' => $url_hash])[0] ?? [];

        if (!empty($file)) {
            $filename = $file['data'];

            try {
                $file['data'] = $this->loadFile($filename);
            } catch (Exception $e) {
            }

            $file['filename'] = $filename;
        }

        return $file;
    }


    public function addFile(string $url, string $contents, array $feed): int
    {
        $finfo = new \finfo(\FILEINFO_MIME);
        $mimetype = $finfo->buffer($contents);
        $content_hash = md5($contents);
        $filename = basename($url);

        $file = [
            'url' => $url,
            'url_hash' => md5($url),
            'filename' => $filename,
            'mimetype' => $mimetype,
            'size' => strlen($contents),
            'cached' => time(),
            'content_hash' => $content_hash
        ];

        $file['content_id'] = $this->addFileContents($content_hash, $contents, $filename, $feed);

        $sql = 'INSERT INTO files (url, url_hash, filename, size, cached, content_hash, mimetype, content_id) VALUES (:url, :url_hash, :filename, :size, :cached, :content_hash, :mimetype, :content_id) ON CONFLICT(url_hash) DO UPDATE SET size=:size, cached=:cached, content_hash=:content_hash, mimetype=:mimetype, content_id=:content_id';
        $this->query($sql, $file);

        $sql = 'SELECT id FROM files WHERE content_hash = :content_hash';
        $fid = $this->query($sql, ['content_hash' => $content_hash])[0]['id'];

        return intval($fid);
    }

    protected function addFileContents(string $content_hash, string $contents, ?string $filename = null, ?array $feed = null): int
    {
        $file_content = [
            'content_hash' => $content_hash,
            'data' => $contents
        ];

        $sql = 'INSERT INTO file_contents (content_hash, data) VALUES (:content_hash, :data) ON CONFLICT(content_hash) DO UPDATE SET id=id';
        $this->query($sql, $file_content);

        $sql = 'SELECT id FROM file_contents WHERE content_hash = :content_hash';
        $fcid = $this->query($sql, ['content_hash' => $content_hash])[0]['id'];

        return $fcid;
    }

    public function deleteFeed(int $feed_id)
    {
        $vars = ['feed_id' => $feed_id];

        $sql = 'DELETE FROM file_contents WHERE id IN (SELECT content_id FROM feeds JOIN files ON feeds.image = files.id WHERE feeds.id = :feed_id)';
        $this->query($sql, $vars);

        $sql = 'DELETE FROM file_contents WHERE id IN (SELECT content_id FROM items LEFT JOIN files ON items.image = files.id WHERE items.feed_id = :feed_id)';
        $this->query($sql, $vars);

        $sql = 'DELETE FROM file_contents WHERE id IN (SELECT content_id FROM items LEFT JOIN files ON items.audio_file = files.id WHERE feed_id = :feed_id)';
        $this->query($sql, $vars);

        $sql = 'DELETE FROM feeds WHERE id = :feed_id';
        $this->query($sql, $vars);

        $this->query('VACUUM');
    }

    public function deleteItemMedia(int $item_id)
    {
        $vars = ['item_id' => $item_id];

        $sql = 'DELETE FROM file_contents WHERE id IN (SELECT content_id FROM items LEFT JOIN files ON items.audio_file = files.id WHERE items.id = :item_id)';
        $this->query($sql, $vars);

        $sql = 'UPDATE items SET audio_file = NULL WHERE id = :item_id';
        $this->query($sql, $vars);

        $this->query('VACUUM');
    }

    public function setItemAudioFile(int $item_id, int $file_id)
    {
        $sql = 'UPDATE items SET audio_file = :file_id WHERE id=:id';
        $this->query($sql, ['id' => $item_id, 'file_id' => $file_id]);
    }

    public function setItemImageFile(int $item_id, int $file_id)
    {
        $sql = 'UPDATE items SET image = :file_id WHERE id=:id';
        $this->query($sql, ['id' => $item_id, 'file_id' => $file_id]);
    }

    public function setFeedImageFile(int $feed_id, int $file_id)
    {
        $sql = 'UPDATE feeds SET image = :file_id WHERE id=:id';
        $this->query($sql, ['id' => $feed_id, 'file_id' => $file_id]);
    }

    public function setPlaybackPosition(int $item_id, int $position): void
    {
        $sql = 'UPDATE items SET playback_position = :position WHERE id = :id';
        $this->query($sql, ['id' => $item_id, 'position' => $position]);
    }

    public function getPlaybackPosition(int $item_id): int
    {
        $sql = 'SELECT playback_position FROM items WHERE id = :id';
        $result = $this->query($sql, ['id' => $item_id]);

        // If the query helper returned false or an empty result set, default to 0
        if (false === $result || empty($result) || !isset($result[0]['playback_position'])) {
            return 0;
        }

        return intval($result[0]['playback_position']);
    }

    public function setItemTranscript(int $item_id, string $transcript): void
    {
        $sql = 'UPDATE items SET transcript = :transcript WHERE id = :id';
        $this->query($sql, ['id' => $item_id, 'transcript' => $transcript]);
    }

    public function setItemAdSections(int $item_id, array $ad_sections): void
    {
        $json = json_encode($ad_sections, JSON_THROW_ON_ERROR);
        $sql = 'UPDATE items SET ad_sections = :ad_sections WHERE id = :id';
        $this->query($sql, ['id' => $item_id, 'ad_sections' => $json]);
    }

    public function clearItemAdSections(int $item_id): void
    {
        $sql = 'UPDATE items SET ad_sections = NULL WHERE id = :id';
        $this->query($sql, ['id' => $item_id]);
    }

    public function getItemTranscript(int $item_id): ?string
    {
        $sql = 'SELECT transcript FROM items WHERE id = :id';
        $result = $this->query($sql, ['id' => $item_id]);
        
        if (false === $result || empty($result)) {
            return null;
        }
        
        return $result[0]['transcript'] ?? null;
    }

    public function getItemAdSections(int $item_id): array
    {
        $sql = 'SELECT ad_sections FROM items WHERE id = :id';
        $result = $this->query($sql, ['id' => $item_id]);
        
        if (false === $result || empty($result) || empty($result[0]['ad_sections'])) {
            return [];
        }
        
        $json = $result[0]['ad_sections'];
        $sections = json_decode($json, true);
        
        return is_array($sections) ? $sections : [];
    }

    protected function loadFile(string $filename): string
    {
        $contents = false;
        if (file_exists($filename)) {
            $contents = file_get_contents($filename);
        }

        if (!$contents) {
            throw new Exception("Could not open: $filename");
        }

        return $contents;
    }

    public function getVersion(): int
    {
        return self::VERSION;
    }

    public function getLibrarySize(): int
    {
        $sql = 'SELECT SUM(size) AS size FROM files';
        $result = $this->query($sql);

        return intval(($result && isset($result[0]['size'])) ? $result[0]['size'] : 0);
    }

    // Job Management Methods
    
    public function createJob(string $type, ?int $feed_id = null, ?int $item_id = null): int
    {
        // Check for duplicate jobs
        if ($this->isDuplicateJob($type, $feed_id, $item_id)) {
            throw new Exception("Duplicate job already running or queued");
        }
        
        // Check for feed refresh rate limiting (60 seconds)
        if ($type === 'refresh_feed' && $feed_id && $this->isRecentFeedRefresh($feed_id)) {
            throw new Exception("Feed was refreshed within the last 60 seconds");
        }
        
        $sql = 'INSERT INTO jobs (type, feed_id, item_id, status) VALUES (:type, :feed_id, :item_id, :status)';
        $this->query($sql, [
            'type' => $type,
            'feed_id' => $feed_id,
            'item_id' => $item_id,
            'status' => 'queued'
        ]);
        
        return intval($this->pdo->lastInsertId());
    }
    
    public function startJob(int $job_id, int $pid): bool
    {
        $sql = 'UPDATE jobs SET status = :status, pid = :pid, started_at = :started_at WHERE id = :id AND status = :old_status';
        $result = $this->query($sql, [
            'id' => $job_id,
            'status' => 'running',
            'pid' => $pid,
            'started_at' => date('Y-m-d H:i:s'),
            'old_status' => 'queued'
        ]);
        
        return $result !== false;
    }
    

    
    public function completeJob(int $job_id, ?float $openai_cost = null): bool
    {
        $sql = 'UPDATE jobs SET status = :status, finished_at = :finished_at, openai_cost = :openai_cost WHERE id = :id';
        $result = $this->query($sql, [
            'id' => $job_id,
            'status' => 'completed',
            'finished_at' => date('Y-m-d H:i:s'),
            'openai_cost' => $openai_cost ?? 0.0
        ]);
        
        return $result !== false;
    }
    
    public function failJob(int $job_id, string $error, ?float $openai_cost = null): bool
    {
        $sql = 'UPDATE jobs SET status = :status, finished_at = :finished_at, error = :error, openai_cost = :openai_cost WHERE id = :id';
        $result = $this->query($sql, [
            'id' => $job_id,
            'status' => 'failed',
            'finished_at' => date('Y-m-d H:i:s'),
            'error' => $error,
            'openai_cost' => $openai_cost ?? 0.0
        ]);
        
        return $result !== false;
    }
    
    public function cancelJob(int $job_id): bool
    {
        $job = $this->getJob($job_id);
        if (!$job) return false;
        
        // Try to kill the process if it's running
        if ($job['status'] === 'running' && $job['pid']) {
            exec("kill -TERM {$job['pid']} 2>/dev/null");
        }
        
        $sql = 'UPDATE jobs SET status = :status, finished_at = :finished_at WHERE id = :id';
        $result = $this->query($sql, [
            'id' => $job_id,
            'status' => 'cancelled',
            'finished_at' => date('Y-m-d H:i:s')
        ]);
        
        return $result !== false;
    }
    
    public function getJob(int $job_id): array
    {
        $sql = 'SELECT * FROM jobs WHERE id = :id';
        $result = $this->query($sql, ['id' => $job_id]);
        return $result !== false && isset($result[0]) ? $result[0] : [];
    }
    
    public function getRunningJobs(): array
    {
        $sql = 'SELECT j.*, f.name as feed_name, i.name as item_name 
                FROM jobs j 
                LEFT JOIN feeds f ON j.feed_id = f.id 
                LEFT JOIN items i ON j.item_id = i.id 
                WHERE j.status IN (:running, :queued) 
                ORDER BY j.created_at DESC';
        $result = $this->query($sql, ['running' => 'running', 'queued' => 'queued']);
        return $result !== false ? $result : [];
    }
    
    public function getAllJobs(int $limit = 50): array
    {
        $sql = 'SELECT j.*, f.name as feed_name, i.name as item_name 
                FROM jobs j 
                LEFT JOIN feeds f ON j.feed_id = f.id 
                LEFT JOIN items i ON j.item_id = i.id 
                ORDER BY j.created_at DESC 
                LIMIT :limit';
        $result = $this->query($sql, ['limit' => $limit]);
        return $result !== false ? $result : [];
    }
    
    public function hasRunningJobs(): bool
    {
        $sql = 'SELECT COUNT(*) as count FROM jobs WHERE status IN (:running, :queued)';
        $result = $this->query($sql, ['running' => 'running', 'queued' => 'queued']);
        return $result !== false && isset($result[0]['count']) ? intval($result[0]['count']) > 0 : false;
    }
    
    public function hasRunningJob(string $type): bool
    {
        $sql = 'SELECT COUNT(*) as count FROM jobs WHERE type = :type AND status IN (:running, :queued)';
        $result = $this->query($sql, [
            'type' => $type,
            'running' => 'running',
            'queued' => 'queued'
        ]);
        return $result !== false && isset($result[0]['count']) ? intval($result[0]['count']) > 0 : false;
    }
    
    private function isDuplicateJob(string $type, ?int $feed_id, ?int $item_id): bool
    {
        $sql = 'SELECT COUNT(*) as count FROM jobs WHERE type = :type AND status IN (:running, :queued)';
        $params = [
            'type' => $type,
            'running' => 'running',
            'queued' => 'queued'
        ];
        
        if ($feed_id !== null) {
            $sql .= ' AND feed_id = :feed_id';
            $params['feed_id'] = $feed_id;
        }
        
        if ($item_id !== null) {
            $sql .= ' AND item_id = :item_id';
            $params['item_id'] = $item_id;
        }
        
        $result = $this->query($sql, $params);
        return $result !== false && isset($result[0]['count']) ? intval($result[0]['count']) > 0 : false;
    }
    
    private function isRecentFeedRefresh(int $feed_id): bool
    {
        $sql = 'SELECT COUNT(*) as count FROM jobs 
                WHERE type = :type AND feed_id = :feed_id 
                AND started_at > datetime("now", "-60 seconds")';
        $result = $this->query($sql, [
            'type' => 'refresh_feed',
            'feed_id' => $feed_id
        ]);
        return $result !== false && isset($result[0]['count']) ? intval($result[0]['count']) > 0 : false;
    }
    
    public function getJobStats(): array
    {
        $sql = 'SELECT 
                    COUNT(*) as total_jobs,
                    COUNT(CASE WHEN status = "running" THEN 1 END) as running_jobs,
                    COUNT(CASE WHEN status = "queued" THEN 1 END) as queued_jobs,
                    COUNT(CASE WHEN status = "completed" THEN 1 END) as completed_jobs,
                    COUNT(CASE WHEN status = "failed" THEN 1 END) as failed_jobs,
                    COUNT(CASE WHEN status = "cancelled" THEN 1 END) as cancelled_jobs,
                    COALESCE(SUM(openai_cost), 0) as total_openai_cost
                FROM jobs';
        $result = $this->query($sql);
        return $result !== false && isset($result[0]) ? $result[0] : [];
    }

    public function getRunningJobForFeed(int $feed_id): ?array
    {
        $sql = 'SELECT j.*, f.name as feed_name FROM jobs j 
                LEFT JOIN feeds f ON j.feed_id = f.id 
                WHERE j.feed_id = :feed_id AND j.status IN ("queued", "running") AND j.type = "refresh_feed"
                ORDER BY j.created_at DESC LIMIT 1';
        $result = $this->query($sql, ['feed_id' => $feed_id]);
        return $result && is_array($result) && !empty($result) ? $result[0] : null;
    }

    public function getRunningJobForItem(int $item_id): ?array
    {
        $sql = 'SELECT j.*, i.name as item_name FROM jobs j 
                LEFT JOIN items i ON j.item_id = i.id 
                WHERE j.item_id = :item_id AND j.status IN ("queued", "running") AND j.type IN ("process_ads", "download_item")
                ORDER BY j.created_at DESC LIMIT 1';
        $result = $this->query($sql, ['item_id' => $item_id]);
        return $result && is_array($result) && !empty($result) ? $result[0] : null;
    }

    public function getFeedsWithoutRunningJobs(): array
    {
        $sql = 'SELECT f.* FROM feeds f 
                LEFT JOIN jobs j ON f.id = j.feed_id AND j.status IN ("queued", "running") AND j.type = "refresh_feed"
                WHERE j.id IS NULL
                ORDER BY f.name';
        $result = $this->query($sql);
        return $result && is_array($result) ? $result : [];
    }

    public function updateJobLog(int $job_id, string $log_message): bool
    {
        $sql = 'UPDATE jobs SET log = COALESCE(log, "") || :log_message WHERE id = :job_id';
        return $this->query($sql, [
            'job_id' => $job_id,
            'log_message' => date('Y-m-d H:i:s') . ': ' . $log_message . "\n"
        ]) !== false;
    }

    public function setJobLog(int $job_id, string $log_content): bool
    {
        $sql = 'UPDATE jobs SET log = :log_content WHERE id = :job_id';
        return $this->query($sql, [
            'job_id' => $job_id,
            'log_content' => $log_content
        ]) !== false;
    }

    public function updateJobCost(int $job_id, float $cost): bool
    {
        $sql = 'UPDATE jobs SET openai_cost = :cost WHERE id = :job_id';
        return $this->query($sql, [
            'job_id' => $job_id,
            'cost' => $cost
        ]) !== false;
    }
    
    public function getItemsNeedingAdProcessing(): array
    {
        // Items need ad processing if:
        // 1. They have an audio file
        // 2. Either they don't have a transcript OR they don't have ad_sections processed yet (NULL or empty string)
        // 3. They are the most recent episode with audio for their feed (to avoid processing all old episodes)
        $sql = 'SELECT items.id, items.name, items.audio_file, items.transcript, items.ad_sections, feeds.name AS feed_name 
                FROM items 
                JOIN feeds ON feeds.id = items.feed_id 
                WHERE items.audio_file IS NOT NULL 
                AND (
                    items.transcript IS NULL OR items.transcript = "" 
                    OR items.ad_sections IS NULL OR items.ad_sections = ""
                )
                AND items.id IN (
                    SELECT i2.id 
                    FROM items i2 
                    WHERE i2.feed_id = items.feed_id 
                    AND i2.audio_file IS NOT NULL
                    ORDER BY i2.published DESC 
                    LIMIT 1
                )
                ORDER BY items.published DESC';
        $result = $this->query($sql);
        
        // No additional filtering needed - if ad_sections is not null/empty string, 
        // it means ad detection was performed (even if result was empty array for ad-free episodes)
        return $result && is_array($result) ? $result : [];
    }
}

