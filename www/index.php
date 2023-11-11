<?php declare(strict_types = 1);

ini_set('display_errors', false);
ini_set('error_reporting', E_ALL);
ini_set('variables_order', 'E');
ini_set('request_order', 'CGP');
ini_set('memory_limit', '-1');

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
#[Route('/', 'GET')]
function home(array $args): void
{
    global $main;
    $feeds = $main->getState()->getFeeds();

    $vars = ['feeds' => $feeds];
    Template::render($main, 'home', $vars);
}

/**
 * Add new feed(s)
 * Path: /add
 * HTTP Method: POST
 *
 * Adds new feed(s) based on entered URL or URLs from an uploaded OPML file.
 */
#[Route('/add', 'POST')]
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

#[Route('/feed', 'GET')]
function feed(array $args): void
{
    global $main;

    $vars = [
        'feed' => $main->getState()->getFeed(intval($args['id'])),
        'items' => $main->getState()->getFeedItems(intval($args['id']))
    ];

    if (empty($vars['feed']) || empty($vars['items'])) {
        $main->setResponseCode(404);
        return;
    }

    Template::render($main, 'feed', $vars);
}

#[Route('/item', 'GET')]
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

#[Route('/delete_feed', 'GET')]
function delete_feed(array $args)
{
    global $main;

    $feed_id = intval($args['feed_id']);
    $main->getState()->deleteFeed($feed_id);

    $main->redirect('/');
}

#[Route('/delete_audio', 'GET')]
function delete_audio(array $args)
{
    global $main;

    $item_id = intval($args['item_id']);
    $main->getState()->deleteItemMedia($item_id);
    $item = $main->getState()->getFeedItem($item_id);

    $main->redirect('/feed?id=' . $item['feed_id']);
}

#[Route('/rss', 'GET')]
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

    Template::renderXml($main, 'rss', $vars);
}

#[Route('/opml', 'GET')]
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

#[Route('/file', 'GET')]
function file_cache(array $args): ?string
{
    global $main;

    $file = new File($main);
    if (!empty($args['file_id'])) {
        $file_data = $file->cacheForId(intval($args['file_id']));
    } else {
        $main->setResponseCode(404);
    }

    if (empty($file_data)) {
        $main->setResponseCode(404);
        return null;
    }

    $data = $file_data['data'];
    $size = strlen($data);

    header('Content-Type: ' . $file_data['mimetype']);
    header('Accept-Ranges: bytes');

    $headers = $main->getHeaders();

    $range = $headers['Range'] ?? null;
    $data = $file_data['data'];
    if (!empty($range)) {
        $range = str_replace('bytes=', '', $range); # 'bytes=0-10'
        $range = explode ('-', $range); # '0-10' => ['0', '10']
        $range = array_map('intval', $range); # ['0', '10'] => [0, 10]
        $start = $range[0];
        $end = $range[1] ?: $size-1;

        $data = substr($data, $start, $end-$start+1 );
        $main->log("$start, $end");

        if (strlen($data) <= $size) {
            $main->setResponseCode(206);
        }

        header("Content-Range: bytes $start-$end/$size");
    }

    header('Content-Length: ' . strlen($data));

    if (array_key_exists('return', $args) && $args['return'] === true) {
        return $data;
    }

    if (array_key_exists('is_head', $args) && $args['is_head'] === true) {
        return null;
    }

    echo $data;

    return null;
}

#[Route('/audio', 'GET')]
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

#[Route('/image', 'GET')]
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


#[Route('/refresh', 'GET')]
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

