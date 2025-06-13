<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \Exception;
use \PDO;

class File
{

    protected Main $main;

    function __construct(Main $main)
    {
        $this->main = $main;
    }

    public function cacheUrl(string $url, array $feed): int|null
    {
        $url_hash = $this->hashUrl($url);
        $cached = $this->cacheForHash($url_hash);

        if (empty($cached)) {
            $file_contents = self::downloadUrl($url);
            $file_id = $this->main->getState()->addFile($url, $file_contents, $feed);
        } else {
            $file_id = $cached['id'];
        }

        return $file_id;
    }

    public function hashUrl(string $url)
    {
        return md5($url);
    }

    public function cacheForId(int $file_id): array
    {
        return $this->main->getState()->getFileById($file_id) ?? [];
    }

    public function cacheForHash(string $url_hash): array
    {
        return $this->main->getState()->getFileByUrlHash($url_hash) ?? [];
    }

    public static function downloadUrl($url, $user = null, $pass = null): string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);

        // Allow reading local files when the `file` scheme is used. This is
        // helpful for tests which operate without network access.
        if ($scheme === 'file' || empty($scheme)) {
            $path = str_replace('file://', '', $url);
            $contents = @file_get_contents($path);
            if (false === $contents) {
                throw new Exception('Cannot download url: ' . $url);
            }

            return $contents;
        }

        $curl = curl_init();

        curl_setopt($curl, \CURLOPT_URL, $url);
        curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, \CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, 300);
        curl_setopt($curl, \CURLOPT_MAXREDIRS, 10);

        if (!empty($user) && !empty($pass)) {
            curl_setopt($curl, \CURLOPT_USERPWD, "$user:$pass");
            curl_setopt($curl, \CURLOPT_HTTPAUTH, \CURLAUTH_ANY);
        }

        $url_contents = curl_exec($curl);
        if (false === $url_contents) {
            throw new Exception('Cannot download url: ' . curl_error($curl));
        }

        return $url_contents;
    }
}
