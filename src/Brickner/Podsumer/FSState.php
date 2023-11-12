<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \Exception;


class FSState extends State
{
    public function getMediaDir(): string
    {
        return $this->main->getInstallPath()
            . $this->main->getConf('podsumer', 'media_dir');
    }

    public function getFeedDir($name): string
    {
        return $this->getMediaDir() . DIRECTORY_SEPARATOR .  $this->escapeFilename($name);
    }

    protected function addFileContents(string $content_hash, string $contents, ?string $filename = null, ?array $feed = null): int
    {
        # Get configured media directory.
        $media_dir = $this->getMediaDir();

        # Check permissions of root media directory.
        if (!is_writable($media_dir)) {
            $made_dir = mkdir($media_dir, 0755, true);

            if (!$made_dir) {
                $message = "Cannot write to media directory at: $media_dir";
                throw new Exception($message);
            }
        }

        # Create dir for feed if needed
        $feed_dir = $this->getFeedDir($feed['name']);
        if (!file_exists($feed_dir)) {
            mkdir($feed_dir, 0755, true);
        }

        # Write file to disk along with image file
        $file_path = $feed_dir . DIRECTORY_SEPARATOR . $filename;
        $written = file_put_contents($file_path, $contents);

        if (!$written) {
            $message = "Cannot write to media to file at: $file_path";
            throw new Exception($message);
        }

        return parent::addFileContents($content_hash, $file_path, $filename, $feed);
    }

    protected function escapeFilename(string $filename): string
    {
        if ($filename === '.' || $filename === '..') {
            return '';
        }

        $filename = str_replace('./', '', $filename);
        $filename = str_replace('../', '', $filename);
        $filename = str_replace('/', '', $filename);

        return $filename;
    }

    public function deleteFeed(int $feed_id)
    {
        $feed = $this->getFeed($feed_id);
        $file_id = $feed['image'];
        $file = $this->getFileById($file_id);
        if ($file['storage_mode'] == 'DISK') {
            unlink($file['filename']);
        }

        $items = $this->getFeedItems($feed_id);
        foreach ($items as $item) {
            $file_id = $item['image'];
            $file = $this->getFileById($file_id);

            if (!empty($file_id)) {
                if ($file['storage_mode'] == 'DISK') {
                    unlink($file['filename']);
                }
            }

            $file_id = $item['audio_file'];
            if (!empty($file_id)) {
                $file = $this->getFileById($file_id);

                if ($file['storage_mode'] == 'DISK') {
                    unlink($file['filename']);
                }
            }
        }

        # Delete feed dir.
        $feed_dir = $this->getFeedDir($feed['name']);
        rmdir($feed_dir);

        parent::deleteFeed($feed_id);
    }

    public function deleteItemMedia(int $item_id)
    {
        $item = $this->getFeedItem($item_id);

        $file_id = $item['audio_file'];
        $file = $this->getFileById($file_id);

        if ($file['storage_mode'] == 'DISK') {
            unlink($file['filename']);
        }

        parent::deleteItemMedia($item_id);
    }
}

