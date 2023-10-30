<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \Exception;
use \JSON_THROW_ON_ERROR;
use \JSON_INVALID_UTF8_IGNORE;
use \JSON_OBJECT_AS_ARRAY;

class State
{
    private Main $main;
    private array $state;
    private $state_file_path;

    function __construct(Main $main)
    {
        $this->main = $main;
        $state_dir = $this->main->getInstallPath() . $this->main->getConf('podsumer', 'state_dir');
        $state_file = $this->main->getConf('podsumer', 'state_file');

        if (!is_dir($state_dir) && !mkdir($state_dir, 0755, true)) {
            throw new Exception("Cannot find or create the state directory: $state_dir");
        }

        $this->state_file_path = $state_dir . DIRECTORY_SEPARATOR . $state_file;

        touch($this->state_file_path);

        $this->loadState();
    }

    function __destruct()
    {
        $this->writeState();
    }

    private function loadState()
    {
        $file_contents = file_get_contents($this->state_file_path);
        if (false === $file_contents) {
            throw new Exception('Cannot read the state file.');
        }

        if (empty($file_contents)) {
            $file_contents = '[]';
        }

        $this->state = json_decode($file_contents, true, 12, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE | JSON_OBJECT_AS_ARRAY);
    }

    private function writeState()
    {
        $file_contents = json_encode($this->state);
        $written = file_put_contents($this->state_file_path, $file_contents);

        if (false === $written) {
            throw new Exception('Cannot write the state file.');
        }
    }

    public function getState(bool $read = true)
    {
        if ($read) {
            $this->loadState();
        }

        return $this->state();
    }

    public function setState(array $state, bool $write = true)
    {
        $this->state = $state;

        if ($write) {
            $this->writeState();
        }
    }

    public function addFeed(Feed $feed)
    {
        $feeds = $this->state['feeds'] ?? [];
        $key = $feed->getUrlHash();

        if (!empty($feeds[$key])) {
           return;
        } else {
            $feed_rec = [];
            $feed_rec['id'] = $feed->getUrlHash();
            $feed_rec['title'] = $feed->getTitle();
            $feed_rec['updated'] = $feed->getPubDate();
            $feed_rec['description'] = $feed->getDescription();
            $feed_rec['channel_art'] = $feed->getChannelArt();
            $feed_rec['items'] = $feed->getItems();
        }

        $feeds[$key] = $feed_rec;

        $this->state['feeds'] = $feeds;

        $this->writeState();
    }

    public function getStateDirPath(): string
    {
        return dirname($this->state_file_path);
    }

    public function getFeeds(): array|false
    {
        return $this->state['feeds'] ?? false;
    }

    public function getFeed(string $id): array
    {
        return $this->state['feeds'][$id];
    }
}
