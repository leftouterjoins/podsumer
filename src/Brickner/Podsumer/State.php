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
    private Main $main;
    private $state_file_path;
    private $sql_dir_path;
    private $pdo;

    function __construct(Main $main)
    {
        $this->main = $main;
        $state_file_path = $main->getInstallPath()
            . $this->main->getConf('podsumer', 'state_file');

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

    protected function installTableS()
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
        $this->installTableS();
    }

    protected function query(string $sql, array $params = []): array
    {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
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
        $feed_rec['image'] = $this->cacheFile($feed->getImage());

        $sql = 'INSERT INTO feeds (url_hash, name, last_update, url, description, image) VALUES (:url_hash, :name, :last_update, :url, :description, :image) ON CONFLICT(url_hash) DO UPDATE SET name=:name, last_update=:last_update, description=:description, image=:image';
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
                'image' => $this->cacheFile($new_item->getImage() ?: '')
            ];

            $sql = 'INSERT INTO items (feed_id, guid, name, published, description, size, audio_url, image) VALUES (:feed_id, :guid, :name, :published, :description, :size, :audio_url, :image) ON CONFLICT(guid) DO UPDATE SET name=:name, published=:published, description=:description, size=:size, audio_url=:audio_url, image=:image';
            $this->query($sql, $item_rec);
        }
    }

    public function cacheFile(string $url): string
    {
        if (!empty($url)) {
            $file = new File($this->main);
            return $file->cacheUrl($url);
        }

        return '';
    }

    public function getStateDirPath(): string
    {
        return dirname($this->state_file_path);
    }

    public function getFeeds(): array|false
    {
        $sql = 'SELECT id, name, last_update, url, description, image FROM feeds ORDER BY last_update DESC';
        return $this->query($sql);
     }

    public function getFeed(int $id): array
    {
        $sql = 'SELECT id, name, description, url, image, last_update FROM feeds WHERE id = :id';
        return $this->query($sql, ['id' => $id])[0] ?? [];
    }

    public function getFeedItem(string $item_id): array
    {
        $sql = 'SELECT name, feed_id, id, audio_url, image, size, published, description FROM items WHERE id = :id';
        return $this->query($sql, ['id' => $item_id])[0];
    }

    public function getFeedItems(int $feed_id): array
    {
        $sql = 'SELECT name, feed_id, id, guid, audio_url, image, size, published, description FROM items WHERE feed_id = :id ORDER BY published DESC';
        return $this->query($sql, ['id' => $feed_id]);
    }

    public function getFeedByHash(string $hash): array
    {
        $sql = 'SELECT id, name, last_update, url, description FROM feeds WHERE url_hash = :hash';
        return $this->query($sql, ['hash' => $hash]);
    }

    public function getFileByUrlHash(string $url_hash): array
    {
        $sql = 'SELECT files.id, url, url_hash, mimetype, filename, size, cached, file_contents.content_hash, file_contents.data FROM files JOIN file_contents ON files.content_hash = file_contents.content_hash WHERE url_hash = :url_hash';
        return $this->query($sql, ['url_hash' => $url_hash])[0] ?? [];
    }

    public function cacheNewFile(string $url, string $contents)
    {
        $finfo = new \finfo(\FILEINFO_MIME);
        $mimetype = $finfo->buffer($contents);
        $file = [
            'url' => $url,
            'url_hash' => md5($url),
            'filename' => basename($url),
            'mimetype' => $mimetype,
            'size' => strlen($contents),
            'cached' => time(),
            'content_hash' => md5($contents),
        ];

        $sql = 'INSERT INTO files (url, url_hash, filename, size, cached, content_hash, mimetype) VALUES (:url, :url_hash, :filename, :size, :cached, :content_hash, :mimetype) ON CONFLICT(url_hash) DO UPDATE SET size=:size, cached=:cached, content_hash=:content_hash, mimetype=:mimetype';
        $this->query($sql, $file);

        $file_content = [
            'content_hash' => md5($contents),
            'data' => $contents
        ];

        $sql = 'INSERT INTO file_contents (content_hash, data) VALUES (:content_hash, :data) ON CONFLICT(content_hash) DO UPDATE SET content_hash=:content_hash';
        $this->query($sql, $file_content);
    }

}

