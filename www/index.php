<?php declare(strict_types = 1);

require_once __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);
ini_set('variables_order', 'E');
ini_set('request_order', 'CGP');
ini_set('memory_limit', -1);

const PODSUMER_PATH = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;

use Brickner\Podsumer\Main;
use Brickner\Podsumer\Template;
use Brickner\Podsumer\Feed;
use Brickner\Podsumer\OPML;

$main = new Main(PODSUMER_PATH);
$main->run();

#[Route('/', 'GET')]
function home(array $args): void
{
    global $main;
    $feeds = $main->getState()->getFeeds();

    $vars = ['feeds' => $feeds];
    Template::render($main, 'home', $vars);
}

#[Route('/add', 'POST')]
function add(array $args): void
{
    global $main;

    if (!empty($args['url'])) {
        $feed = new Feed($main, $args['url']);
        $main->getState()->addFeed($feed);
    }

    $uploads = $main->getUploads();
    if (!empty($uploads['opml'])) {
        $feed_urls = OPML::parse($main, $uploads['opml']);
        foreach ($feed_urls as $url) {
            $feed = new Feed($main, $url);
            $main->getState()->addFeed($feed);
        }
    }

    header("Location: /");
    die();

}

#[Route('/feed', 'GET')]
function feed(array $args): void
{
    global $main;

    $vars = [
        'feed' => $main->getState()->getFeed(intval($args['id'])),
        'items' => $main->getState()->getFeedItems($args['id'])
    ];

    Template::render($main, 'feed', $vars);
}

#[Route('/item', 'GET')]
function item(array $args): void
{
    global $main;

    $item = $main->getState()->getFeedItem($args['item_id']);
    $feed = $main->getState()->getFeed($item['feed_id']);

    $vars = [
        'item' => $item,
        'feed_image' => $feed['image']
    ];

    Template::render($main, 'item', $vars);
}

