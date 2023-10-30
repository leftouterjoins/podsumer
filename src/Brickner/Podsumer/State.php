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

        // Do the tables expected exists?
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
        $feed_lookup = $this->getFeedByHash($feed_url_hash);

        if (!empty($feed_lookup)) {
           return;
        } else {
            $feed_rec = [];
            $feed_rec['url_hash'] = $feed_url_hash;
            $feed_rec['url'] = $feed->getUrl();
            $feed_rec['name'] = $feed->getTitle();
            $feed_rec['last_update'] = $feed->getPubDate();
            $feed_rec['description'] = $feed->getDescription();
            $feed_rec['image'] = $this->encodeImage($feed->getChannelArt());
        }

        $sql = 'INSERT INTO feeds (url_hash, name, last_update, url, description, image) VALUES (:url_hash, :name, :last_update, :url, :description, :image)';
        $this->query($sql, $feed_rec);
        $feed_id = $this->pdo->lastInsertId();
        $feed->setFeedId(intval($feed_id));

        $items = $feed->getFeedItems();
        $this->addFeedItems($items, $feed);
    }

    protected function addFeedItems(\SimpleXMLElement $items, Feed $feed)
    {
        foreach ($items as $item) {
            $new_item = new Item($this->main, $item, $feed);
            $item_rec = [
                'feed_id' => $feed->getFeedId(),
                'name' => $new_item->getName(),
                'published' => $new_item->getPublished(),
                'description' => $new_item->getDescription(),
                'size' => $new_item->getSize(),
                'audio_url' => $new_item->getAudioFileUrl(),
                'image' => $this->encodeImage($new_item->getImage() ?: '')
            ];

            $sql = 'INSERT INTO items (feed_id, name, published, description, size, audio_url, image) VALUES (:feed_id, :name, :published, :description, :size, :audio_url, :image)';
            $this->query($sql, $item_rec);
        }
    }

    public function encodeImage(string $art): string
    {
        if (!empty($art)) {
            $image_bin = file_get_contents($art);
            $image = base64_encode($image_bin);
            $finfo = new \finfo(FILEINFO_MIME);
            $mime = $finfo->buffer($image_bin);
            return "data:$mime;base64,$image";
        }

        return '';
    }

    public function getStateDirPath(): string
    {
        return dirname($this->state_file_path);
    }

    public function getFeeds(): array|false
    {
        $sql = 'SELECT id, name, last_update, url, description, image FROM feeds';
        return $this->query($sql);
     }

    public function getFeed(int $id): array
    {
        $sql = 'SELECT id, name, description, url, image, last_update FROM feeds WHERE id = :id';
        return $this->query($sql, ['id' => $id])[0];
    }

    public function getFeedItem(string $item_id): array
    {
        $sql = 'SELECT name, feed_id, id, audio_url, image, size, published, description FROM items WHERE id = :id';
        return $this->query($sql, ['id' => $item_id])[0];
    }

    public function getFeedItems(string $feed_id): array
    {
        $sql = 'SELECT name, feed_id, id, audio_url, image, size, published, description FROM items WHERE feed_id = :id';
        return $this->query($sql, ['id' => $feed_id]);
    }

    public function getFeedByHash(string $hash): array
    {
        $sql = 'SELECT id, name, last_update, url, description FROM feeds WHERE url_hash = :hash';
        return $this->query($sql, ['hash' => $hash]);
    }
}

