<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \Exception;


class FSState extends State
{
    public function getMediaDir(): string
    {
        return $this->main->getConf('podsumer', 'media_dir');
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

            if (!file_exists($media_dir)) {

                error_clear_last();

                $made_dir = mkdir($media_dir, 0755, true);

                $error = error_get_last();
                if (!empty($error)) {
                    $message = "Cannot create media directory at: $media_dir";
                    throw new Exception($message);
                }

                if (!$made_dir) {
                    $message = "Cannot write to media directory at: $media_dir";
                    throw new Exception($message);
                }
            }

            $modified_perms = chmod($media_dir, 0755);
            if (false === $modified_perms) {
                $message = "Cannot modify permissions of media directory at: $media_dir";
                throw new Exception($message);
            }
        }

        # Create dir for feed if needed
        $feed_dir = $this->getFeedDir($feed['name']);
        if (!file_exists($feed_dir)) {
            error_clear_last();

            @mkdir($feed_dir, 0755, true);
            $error = error_get_last();
            if (!empty($error)) {
                $message = "Cannot create feed directory at: $feed_dir";
                throw new Exception($message);
            }

        }

        # Write file to disk along with image file
        $file_path = $feed_dir . DIRECTORY_SEPARATOR . $filename;

        error_clear_last();
        $written = @file_put_contents($file_path, $contents);

        $error = error_get_last();

        if (!$written || !empty($error)) {
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

        # Capture all related files before we alter the database so we can
        # safely remove them from disk afterwards.
        $files_to_delete = [];

        $image_file_id = $feed['image'] ?? null;
        if ($image_file_id) {
            $files_to_delete[] = $this->getFileById($image_file_id);
        }

        # Collect images / audio from each item before DB deletion
        foreach ($this->getFeedItems($feed_id) as $item) {
            foreach (['image', 'audio_file'] as $col) {
                $fid = $item[$col] ?? null;
                if ($fid) {
                    $files_to_delete[] = $this->getFileById($fid);
                }
            }
        }

        # Delete from the database first (cascades will clean up related rows)
        parent::deleteFeed($feed_id);

        # Remove the on-disk files we captured earlier
        foreach ($files_to_delete as $file) {
            if (!empty($file) && ($file['storage_mode'] ?? null) === 'DISK') {
                $filename = $file['filename'] ?? null;
                if (!empty($filename) && file_exists($filename)) {
                    @unlink($filename);
                }
            }
        }

        # Finally, try to remove the (now empty) feed directory
        $feed_dir = $this->getFeedDir($feed['name'] ?? '') ?: null;
        if ($feed_dir && file_exists($feed_dir)) {
            $files_in_dir = array_diff(scandir($feed_dir), ['.', '..']);
            if (empty($files_in_dir)) {
                @rmdir($feed_dir);
            }
        }
    }

    public function deleteItemMedia(int $item_id)
    {
        $item = $this->getFeedItem($item_id);

        $file_id = $item['audio_file'];

        if (empty($file_id)) {
            return;
        }

        $file = $this->getFileById($file_id);

        if (!empty($file) && ($file['storage_mode'] ?? null) === 'DISK') {
            $filename = $file['filename'] ?? null;

            if (!empty($filename) && file_exists($filename)) {
                @unlink($filename);
            }
        }

        parent::deleteItemMedia($item_id);
    }
}

