<?php declare(strict_types = 1);

ini_set('display_errors', true);
ini_set('error_reporting', E_ALL);
ini_set('variables_order', 'E');
ini_set('request_order', 'CGP');
ini_set('memory_limit', '-1');
ini_set('max_execution_time', 360);

# Detect install directory.
const PODSUMER_PATH = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;

# Load composer auto-loader.
require_once PODSUMER_PATH . 'vendor/autoload.php';

use Brickner\Podsumer\Feed;
use Brickner\Podsumer\File;
use Brickner\Podsumer\Main;
use Brickner\Podsumer\OPML;
use Brickner\Podsumer\Template;

# Create the application.
$main = new Main(PODSUMER_PATH, array_merge($_SERVER, $_ENV), array_merge($_GET, $_POST), $_FILES);
$main->run();

/**
 * Home
 * Path: /
 * HTTP Method: GET
 *
 * Renders the default page.
 */
#[Route('/', 'GET', true)]
function home(array $args): void
{
    global $main;
    $feeds = $main->getState()->getFeeds();

    $vars = ['feeds' => $feeds];
    Template::render($main, 'home', $vars);
}

#[Route('/episodes', 'GET', true)]
function episodes(array $args): void
{
    global $main;
    $page = isset($args['page']) ? max(1, intval($args['page'])) : 1;
    $per_page = intval($main->getConf('podsumer', 'items_per_page')) ?: 10;

    $vars = [
        'items' => $main->getState()->getAllItemsPage($per_page, $page),
        'page' => $page,
        'page_count' => max(1, ceil($main->getState()->countAllItems() / $per_page))
    ];

    Template::render($main, 'episodes', $vars);
}

/**
 * Add new feed(s)
 * Path: /add
 * HTTP Method: POST
 *
 * Adds new feed(s) based on entered URL or URLs from an uploaded OPML file.
 */
#[Route('/add', 'POST', true)]
function add(array $args): void
{
    global $main;

    # Add a single feed via a URL. URL is validated automatically.

    if (!empty($args['url'])) {
        $feed = new Feed($args['url']);
        $main->getState()->addFeed($feed);
    }

    # Add an array of feeds via uploaded OPML file.

    $uploads = $main->getUploads();

    if (count(array_filter($uploads['opml'])) > 2) {

        $feed_urls = OPML::parse($uploads['opml']);

        foreach ($feed_urls as $url) {
            $feed = new Feed($url);
            $main->getState()->addFeed($feed);
        }
    }

    # Send user to home to see newly added feed(s).

    $main->redirect('/');
}

#[Route('/feed', 'GET', true)]
function feed(array $args): void
{
    global $main;

    $page = isset($args['page']) ? max(1, intval($args['page'])) : 1;
    $per_page = intval($main->getConf('podsumer', 'items_per_page')) ?: 10;
    $feed_id = intval($args['id']);

    $vars = [
        'feed' => $main->getState()->getFeed($feed_id),
        'items' => $main->getState()->getFeedItemsPage($feed_id, $per_page, $page),
        'page' => $page,
        'page_count' => max(1, ceil($main->getState()->countFeedItems($feed_id) / $per_page))
    ];

    if (empty($vars['feed']) || empty($vars['items'])) {
        $main->setResponseCode(404);
        return;
    }

    Template::render($main, 'feed', $vars);
}

#[Route('/item', 'GET', true)]
function item(array $args): void
{
    global $main;

    if (empty($args['item_id'])) {
        $main->setResponseCode(404);
        return;
    }

    $item = $main->getState()->getFeedItem(intval($args['item_id']));
    $feed = $main->getState()->getFeed(intval($item['feed_id']));

    $vars = [
        'item' => $item,
        'feed' => $feed
    ];

    if (empty($vars['feed']) || empty($vars['item'])) {
        $main->setResponseCode(404);
        return;
    }

    Template::render($main, 'item', $vars);
}

#[Route('/delete_feed', 'GET', true)]
function delete_feed(array $args)
{
    global $main;

    $feed_id = intval($args['feed_id']);
    $main->getState()->deleteFeed($feed_id);

    $main->redirect('/');
}

#[Route('/delete_audio', 'GET', true)]
function delete_audio(array $args)
{
    global $main;

    $item_id = intval($args['item_id']);
    $main->getState()->deleteItemMedia($item_id);
    $item = $main->getState()->getFeedItem($item_id);

    $main->redirect('/feed?id=' . $item['feed_id']);
}

#[Route('/rss', ['GET', 'HEAD'])]
function rss(array $args)
{
    global $main;

    if (empty($args['feed_id'])) {
        $main->setResponseCode(404);
        return;
    }

    doRefresh(intval($args['feed_id']));

    $feed_id = intval($args['feed_id']);

    $items = $main->getState()->getFeedItems($feed_id);
    $feed = $main->getState()->getFeed($feed_id);

    $vars = [
        'items' => $items,
        'feed' => $feed,
        'host' => $main->getBaseUrl()
    ];

    if (empty($vars['feed']) || empty($vars['items'])) {
        $main->setResponseCode(404);
        return;
    }

    // Set proper Content-Type for RSS feeds
    header('Content-Type: application/rss+xml; charset=utf-8');
    
    // Add Last-Modified header based on feed update time
    $lastModified = is_numeric($feed['last_update']) ? intval($feed['last_update']) : strtotime($feed['last_update']);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
    
    // Generate ETag based on feed content
    $etag = '"' . md5($feed['id'] . $feed['last_update'] . count($items)) . '"';
    header('ETag: ' . $etag);
    
    // Add cache headers
    header('Cache-Control: public, max-age=300'); // 5 minutes
    
    // Handle conditional requests
    $headers = $main->getHeaders();
    $ifNoneMatch = $headers['If-None-Match'] ?? null;
    $ifModifiedSince = $headers['If-Modified-Since'] ?? null;
    
    if ($ifNoneMatch === $etag || 
        ($ifModifiedSince && strtotime($ifModifiedSince) !== false && strtotime($ifModifiedSince) >= $lastModified)) {
        $main->setResponseCode(304); // Not Modified
        return;
    }
    
    // Handle HEAD requests - return headers only
    if ($main->getMethod() === 'HEAD') {
        return;
    }

    Template::renderXml($main, 'rss', $vars);
}

#[Route('/opml', 'GET', true)]
function opml(array $args)
{
    global $main;

    $feeds = $main->getState()->getFeeds();

    $vars = [
        'feeds' => $feeds,
        'host' => $main->getBaseUrl()
    ];

    header("Content-disposition: attachment; filename=\"podsumer.opml\"");
    header("Content-Type: text/x-opml");

    Template::renderXml($main, 'opml', $vars);
}

#[Route('/file', ['GET', 'HEAD'])]
function file_cache(array $args): ?string
{
    global $main;

    $file = new File($main);

    if (!empty($args['file_id'])) {
        $file_data = $file->cacheForId(intval($args['file_id']));

    } else {
        $main->setResponseCode(404);
        return null;
    }

    if (empty($file_data)) {
        $main->setResponseCode(404);
        return null;
    }

    $data = $file_data['data'];
    $size = strlen($data);

    // Fix mimetype for binary files - remove charset parameter
    $mimetype = $file_data['mimetype'];
    if (str_starts_with($mimetype, 'audio/') || 
        str_starts_with($mimetype, 'image/') || 
        str_starts_with($mimetype, 'video/')) {
        // Remove charset parameter from binary files
        $mimetype = explode(';', $mimetype)[0];
    }

    header('Content-Type: ' . $mimetype);
    header('Accept-Ranges: bytes');

    // Add CORS headers for cross-origin access
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, HEAD');
    header('Access-Control-Allow-Headers: Range');

    // Add Content-Disposition header for better compatibility
    $filename = $file_data['filename'] ?? 'audio.mp3';
    header('Content-Disposition: inline; filename="' . $filename . '"');
    
    // Add cache control headers
    header('Cache-Control: public, max-age=31536000'); // 1 year
    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
    
    // Add ETag for cache validation
    $etag = '"' . md5($file_data['url'] . $file_data['cached']) . '"';
    header('ETag: ' . $etag);
    
    // Add Last-Modified header
    $lastModified = is_numeric($file_data['cached']) ? intval($file_data['cached']) : strtotime($file_data['cached']);
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

    $headers = $main->getHeaders();
    
    // Handle conditional requests for caching
    $ifNoneMatch = $headers['If-None-Match'] ?? null;
    $ifModifiedSince = $headers['If-Modified-Since'] ?? null;
    
    if ($ifNoneMatch === $etag || 
        ($ifModifiedSince && strtotime($ifModifiedSince) !== false && strtotime($ifModifiedSince) >= $lastModified)) {
        $main->setResponseCode(304); // Not Modified
        return null;
    }

    $range = $headers['Range'] ?? null;
    $data = $file_data['data'];
    if (!empty($range)) {
        $range = str_replace('bytes=', '', $range); # 'bytes=0-10'
        $range = explode ('-', $range); # '0-10' => ['0', '10']
        $start = intval($range[0]);
        $end = (!empty($range[1])) ? intval($range[1]) : $size - 1;

        // Validate range
        if ($start >= $size || $end >= $size || $start > $end) {
            $main->setResponseCode(416); // Range Not Satisfiable
            header("Content-Range: bytes */$size");
            return null;
        }

        $data = substr($data, $start, $end - $start + 1);
        $main->setResponseCode(206); // Partial Content
        header("Content-Range: bytes $start-$end/$size");
    }

    header('Content-Length: ' . strlen($data));

    // Handle HEAD requests
    if ($main->getMethod() === 'HEAD') {
        return null;
    }

    if (array_key_exists('return', $args) && $args['return'] === true) {
        return $data;
    }

    if (array_key_exists('is_head', $args) && $args['is_head'] === true) {
        return null;
    }

    echo $data;

    return null;
}

#[Route('/audio', ['GET', 'HEAD'])]
function audio_cache(array $args)
{
    global $main;

    if (empty($args['item_id'])) {
        $main->setResponseCode(404);
        return;
    }

    $item_id = intval($args['item_id']);
    $item = $main->getState()->getFeedItem($item_id);

    $feed = $main->getState()->getFeedForItem($item_id);

    $file = new File($main);
    $file_id = $file->cacheUrl($item['audio_url'], $feed);

    $main->getState()->setItemAudioFile($item_id, $file_id);

    file_cache(['file_id' => $file_id]);
}

#[Route('/image', ['GET', 'HEAD'])]
function image_cache(array $args)
{
    global $main;

    if (array_key_exists('item_id', $args)) {

        $item_id = intval($args['item_id']);
        $item = $main->getState()->getFeedItem($item_id);
        $feed = $main->getState()->getFeed($item['feed_id']);

        $file_id = $item['image'];

    } elseif (array_key_exists('feed_id', $args)) {

        $feed_id = intval($args['feed_id']);
        $feed = $main->getState()->getFeed($feed_id);

        $file_id = $feed['image'];

    } else {

        $main->setResponseCode(404);
        return;
    }

    $file_data = $main->getState()->getFileById($file_id);

    $file = new File($main);
    $file_id = $file->cacheUrl($file_data['url'], $feed);

    if (array_key_exists('item_id', $args)) {
        $main->getState()->setItemImageFile($item_id, $file_id);
    } elseif (array_key_exists('feed_id', $args)) {
        $main->getState()->setFeedImageFile($feed_id, $file_id);
    }

    file_cache(['file_id' => $file_id]);
}

#[Route('/refresh', 'GET', true)]
function refresh(array $args)
{
    global $main;

    if (empty($args['feed_id'])) {
        $main->setResponseCode(404);
        return;
    }

    doRefresh(intval($args['feed_id']));

    $main->redirect('/feed?id=' . intval($args['feed_id']));
}

function doRefresh(int $feed_id) {

    global $main;

    if (!empty($feed_id)) {
        $feed = $main->getState()->getFeed(intval($feed_id));
        $refresh_feed = new Feed($feed['url'] ?? null);
        $refresh_feed->setFeedId(intval($feed_id));
        $main->getState()->addFeed($refresh_feed);
    }
}

#[Route('/get_playback', 'GET', true)]
function get_playback(array $args): void
{
    global $main;

    if (empty($args['item_id'])) {
        $main->setResponseCode(404);
        return;
    }

    $pos = $main->getState()->getPlaybackPosition(intval($args['item_id']));

    header('Content-Type: application/json');
    echo json_encode(['position' => intval($pos)]);
}

#[Route('/set_playback', 'POST', true)]
function set_playback(array $args): void
{
    global $main;

    if (empty($args['item_id']) || !isset($args['position'])) {
        $main->setResponseCode(404);
        return;
    }

    $main->getState()->setPlaybackPosition(intval($args['item_id']), intval($args['position']));
}

