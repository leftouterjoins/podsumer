<?php declare(strict_types = 1);

require_once __DIR__ . '/../vendor/autoload.php';

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);
ini_set('variables_order', 'E');
ini_set('request_order', 'CGP');

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
        'feed' => $main->getState()->getFeed($args['id'])
    ];

    Template::render($main, 'feed', $vars);
}

#[Route('/item', 'GET')]
function item(array $args): void
{
    global $main;

    $feed = $main->getState()->getFeed($args['feed_id']);

    $vars = [
        'feed_id' => $args['feed_id'],
        'item' => array_filter($feed['items'][$args['item_id']]),
        'feed_art_url' => $feed['channel_art']
    ];

    Template::render($main, 'item', $vars);
}
