<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

class PodcastIndex
{
    static public function search(string $query, int $max, string $key, string $secret, ?int $time = null): array
    {
        if (empty($key) || empty($secret) || empty($query)) {
            return [];
        }

        $time = $time ?? time();
        $hash = sha1($key . $secret . $time);

        $headers = [
            'User-Agent: Podsumer',
            'X-Auth-Date: ' . $time,
            'X-Auth-Key: ' . $key,
            'Authorization: ' . $hash,
        ];

        $url = 'https://api.podcastindex.org/api/1.0/search/byterm?q=' . urlencode($query) . '&max=' . $max;

        $curl = curl_init();
        curl_setopt($curl, \CURLOPT_URL, $url);
        curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, \CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, \CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, 30);

        $result = curl_exec($curl);
        if (false === $result) {
            return [];
        }

        $data = json_decode($result, true);
        if (!is_array($data) || !array_key_exists('feeds', $data)) {
            return [];
        }

        return $data['feeds'];
    }
}

