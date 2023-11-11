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

        $this->checkDBInstall();
    }

    protected function installTables()
    {
        $table_sql = file_get_contents($this->sql_dir_path . '/tables.sql');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
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
            return false;
        }
    }

    public function addFeed(Feed $feed)
    {
        if (!$feed->feedLoaded()) {
            return;
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

        $sql = 'INSERT INTO feeds (url_hash, name, last_update, url, description, image, image_url) VALUES (:url_hash, :name, :last_update, :url, :description, :image, :image_url) ON CONFLICT(url_hash) DO UPDATE SET name=:name, last_update=:last_update, description=:description, image=:image, image_url=:image_url';
        $this->query($sql, $feed_rec);
        $feed_id = $this->pdo->lastInsertId();
        if ('0' !== $feed_id) {
            $feed->setFeedId(intval($feed_id));
        }

        $items = $feed->getFeedItems();
        $this->addFeedItems($items, $feed);
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
        $sql = 'SELECT items.name, items.feed_id, items.id, items.guid, items.audio_url, items.audio_file, COALESCE(items.image, feeds.image) AS image, items.size, items.published, items.description FROM items JOIN feeds ON feeds.id = items.feed_id WHERE items.id = :id ORDER BY items.published DESC';
        return $this->query($sql, ['id' => $item_id])[0];
    }

    public function getFeedItems(int $feed_id): array
    {
        $sql = 'SELECT items.name, items.feed_id, items.id, items.guid, items.audio_url, items.audio_file, COALESCE(items.image, feeds.image) AS image, items.size, items.published, items.description FROM items JOIN feeds ON feeds.id = items.feed_id WHERE items.feed_id = :id ORDER BY items.published DESC';
        return $this->query($sql, ['id' => $feed_id]);
    }

    public function getFeedByHash(string $hash): array
    {
        $sql = 'SELECT id, name, last_update, url, description FROM feeds WHERE url_hash = :hash';
        return $this->query($sql, ['hash' => $hash]);
    }

    public function getFileById(int $file_id): array
    {
        $sql = 'SELECT files.id, url, url_hash, mimetype, filename, size, cached, storage_mode, file_contents.content_hash, file_contents.data FROM files JOIN file_contents ON files.content_hash = file_contents.content_hash WHERE files.id = :file_id';

        return $this->query($sql, ['file_id' => $file_id])[0] ?? [];
    }

    public function getFileByUrlHash(string $url_hash): array
    {
        $sql = 'SELECT files.id, url, url_hash, mimetype, filename, size, cached, storage_mode, file_contents.content_hash, file_contents.data FROM files JOIN file_contents ON files.content_hash = file_contents.content_hash WHERE url_hash = :url_hash';
        return $this->query($sql, ['url_hash' => $url_hash])[0] ?? [];
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
            'content_hash' => $content_hash,
            'storage_mode' => ($this->main->getConf('podsumer', 'store_media_on_disk'))
                ? 'DISK'
                : 'DB'
        ];

        $file['content_id'] = $this->addFileContents($content_hash, $contents, $filename, $feed);

        $sql = 'INSERT INTO files (url, url_hash, filename, size, cached, content_hash, mimetype, content_id, storage_mode) VALUES (:url, :url_hash, :filename, :size, :cached, :content_hash, :mimetype, :content_id, :storage_mode) ON CONFLICT(url_hash) DO UPDATE SET size=:size, cached=:cached, content_hash=:content_hash, mimetype=:mimetype, content_id=:content_id, storage_mode=:storage_mode';
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

        $sql = 'INSERT INTO file_contents (content_hash, data) VALUES (:content_hash, :data) ON CONFLICT(content_hash) DO UPDATE SET content_hash=:content_hash';
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
        $sql = 'UPDATE feed SET image = :file_id WHERE id=:id';
        $this->query($sql, ['id' => $feed_id, 'file_id' => $file_id]);
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
}

