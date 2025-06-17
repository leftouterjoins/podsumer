<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

/**
 * Lightweight helper for querying YouTube search without an API key.
 *
 * This mirrors the technique used by yt-dlp: it talks to the undocumented
 * InnerTube "youtubei" endpoint with a built-in web client key.
 *
 * For our purposes we only need the first *video* result to fetch a videoId
 * we can then feed to SponsorBlock.
 */
class YouTubeSearch
{
    /**
     * Publicly-exposed method – returns the first videoId that matches `$query`.
     *
     * @param string $query       Search string.
     * @param int    $maxResults  Stop searching after we collected this many videoIds.
     *
     * @return array<string>      Array of videoIds – can be empty if nothing found.
     */
    public static function search(string $query, int $maxResults = 1): array
    {
        if (empty($query)) {
            return [];
        }

        $results   = [];
        $continuation = null;

        // We loop until we either exhaust available pages *or* gather $maxResults.
        while (count($results) < $maxResults) {
            $json = self::makeRequest($query, $continuation);
            if (!$json) {
                break; // network / decode failure – give up early
            }

            self::extractVideoIds($json, $results, $maxResults);

            // Continuation token?
            $continuation = self::getContinuationToken($json);
            if (!$continuation) {
                break; // no more pages
            }
            // Only send the continuation on subsequent iterations – unset $query so makeRequest knows which variant.
            $query = '';
        }

        return $results;
    }

    /* --------------------------- Internal helpers --------------------------- */

    private const INNER_TUBE_KEY       = 'AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8';
    private const INNER_TUBE_BASE      = 'https://www.youtube.com/youtubei/v1/search?key=';
    private const CLIENT_NAME          = 'WEB';
    private const CLIENT_VERSION       = '2.20240616.01.00'; // reasonably up-to-date, can be bumped later

    /**
     * Performs a POST to the InnerTube search endpoint. If $continuation is
     * provided, we send that instead of the initial query.
     *
     * @return array<string,mixed>|null  Decoded JSON as associative array or null on failure.
     */
    private static function makeRequest(string $query, ?string $continuation = null): ?array
    {
        $payload = [
            'context' => [
                'client' => [
                    'clientName'    => self::CLIENT_NAME,
                    'clientVersion' => self::CLIENT_VERSION,
                    // We could add hl/gl if localisation is needed.
                ],
            ],
        ];

        if ($continuation) {
            $payload['continuation'] = $continuation;
        } else {
            $payload['query']  = $query;
            // Filter to *videos only* to simplify downstream parsing.
            //   Videos-only filter encoded param – same string yt-dlp uses.
            $payload['params'] = 'EgIQAQ%3D%3D';
        }

        $jsonPayload = json_encode($payload);
        if ($jsonPayload === false) {
            return null;
        }

        $headers = [
            'Content-Type: application/json',
            // UA that resembles a mainstream browser to avoid suspicion.
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
        ];

        $curl = curl_init();
        curl_setopt($curl, \CURLOPT_URL, self::INNER_TUBE_BASE . self::INNER_TUBE_KEY);
        curl_setopt($curl, \CURLOPT_POST, true);
        curl_setopt($curl, \CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, \CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($curl, \CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, \CURLOPT_CONNECTTIMEOUT, 15);
        curl_setopt($curl, \CURLOPT_TIMEOUT, 30);

        $response = curl_exec($curl);
        curl_close($curl);

        if (false === $response) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Walks the response tree and appends up to $max videoIds onto $out.
     *
     * @param array<string,mixed>  $json
     * @param array<string>        $out
     * @param int                  $max
     */
    private static function extractVideoIds(array $json, array &$out, int $max): void
    {
        $contentsPointer = $json['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] ?? null;
        if (!is_array($contentsPointer)) {
            return;
        }

        foreach ($contentsPointer as $section) {
            $items = $section['itemSectionRenderer']['contents'] ?? [];
            foreach ($items as $item) {
                if (isset($item['videoRenderer'])) {
                    $vid = $item['videoRenderer']['videoId'] ?? null;
                    if ($vid && !in_array($vid, $out, true)) {
                        $out[] = $vid;
                        if (count($out) >= $max) {
                            return;
                        }
                    }
                }
            }
        }
    }

    /**
     * Attempts to fetch the continuation token from a response if present.
     */
    private static function getContinuationToken(array $json): ?string
    {
        $sectionList = $json['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] ?? [];
        foreach ($sectionList as $section) {
            if (isset($section['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'])) {
                return strval($section['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token']);
            }
        }
        // Sometimes token is elsewhere – also inspect onResponseReceivedCommands collection.
        $commands = $json['onResponseReceivedCommands'] ?? [];
        foreach ($commands as $command) {
            if (isset($command['appendContinuationItemsAction']['continuationItems'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token'])) {
                return strval($command['appendContinuationItemsAction']['continuationItems'][0]['continuationItemRenderer']['continuationEndpoint']['continuationCommand']['token']);
            }
        }
        return null;
    }
} 