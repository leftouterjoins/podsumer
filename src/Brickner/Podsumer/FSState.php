<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \Exception;


class FSState extends State
{
    protected function addFileContents(string $content_hash, string $contents, ?string $filename = null, ?array $feed = null): int
    {
        # Get configured media directory.
        $media_dir = $this->main->getInstallPath()
            . $this->main->getConf('podsumer', 'media_dir');

        # Check permissions of root media directory.
        if (!is_writable($media_dir)) {
            $made_dir = mkdir($media_dir);

            if (!$made_dir) {
                $message = "Cannot write to media directory at: $media_dir";
                throw new Exception($message);
            }
        }

        # Create dir for feed if needed
        $feed_dir = $media_dir . DIRECTORY_SEPARATOR .  $this->escapeFilename($feed['name']);
        if (!file_exists($feed_dir)) {
            mkdir($feed_dir);
        }

        # Write file to disk along with image file
        $file_path = $feed_dir . DIRECTORY_SEPARATOR . $this->escapeFilename($filename);
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
}

