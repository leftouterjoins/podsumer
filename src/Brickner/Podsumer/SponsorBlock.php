<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

/**
 * Very small wrapper around the public SponsorBlock API
 *   https://wiki.sponsor.ajay.app/
 *
 * We currently only use it to obtain *skip segments* for a YouTube video so we
 * can automatically skip sponsor reads during podcast playback.
 */
class SponsorBlock
{
    /**
     * @param string   $videoId      YouTube video ID.
     * @param string[] $categories   Array of category slugs we care about.
     *                               Defaults to the standard sponsor + selfpromo + interaction set.
     *
     * @return array<int,array{segment:array{0:float,1:float},category:string}> | []
     */
    public static function getSegments(string $videoId, array $categories = ['sponsor', 'selfpromo', 'interaction', 'intro', 'outro']): array
    {
        $categories = ['sponsor', 'selfpromo', 'interaction', 'intro', 'outro'];
        if (empty($videoId)) {
            return [];
        }

        $url = 'https://sponsor.ajay.app/api/skipSegments?videoID=' . urlencode($videoId) . '&categories=' . rawurlencode(json_encode($categories));

        $curl = curl_init();
        curl_setopt($curl, \CURLOPT_URL, $url);
        curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, \CURLOPT_HTTPHEADER, [
            'User-Agent: Podsumer',
            'Accept: application/json',
        ]);
        curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, \CURLOPT_TIMEOUT, 20);

        $response = curl_exec($curl);
        curl_close($curl);

        if (false === $response) {
            return [];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }
} 