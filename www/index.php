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
use Brickner\Podsumer\PodcastIndex;
use Brickner\Podsumer\AdDetection;

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

#[Route('/search', 'GET', true)]
function search(array $args): void
{
    global $main;

    $page = isset($args['page']) ? max(1, intval($args['page'])) : 1;
    $per_page = intval($main->getConf('podsumer', 'items_per_page')) ?: 10;
    $q = $args['q'] ?? '';

    $results = [];
    $page_count = 1;

    if (!empty($q)) {
        $key = strval($main->getConf('podsumer', 'podcastindex_key'));
        $secret = strval($main->getConf('podsumer', 'podcastindex_secret'));
        $all = PodcastIndex::search($q, 1000, $key, $secret);
        $page_count = max(1, intval(ceil(count($all) / $per_page)));
        $results = array_slice($all, ($page - 1) * $per_page, $per_page);
    }

    $vars = [
        'feeds' => $results,
        'q' => $q,
        'page' => $page,
        'page_count' => $page_count
    ];

    Template::render($main, 'search', $vars);
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
        $feed_id = $main->getState()->addFeed($feed);
        
        // Create a background job to refresh the feed (which will trigger automatic download)
        if ($feed_id > 0) {
            createRefreshJobForNewFeed($main, $feed_id);
        }
    }

    # Add an array of feeds via uploaded OPML file.

    $uploads = $main->getUploads();

    // Only attempt to process OPML file if it was actually uploaded
    if (isset($uploads['opml']) && is_array($uploads['opml']) && count(array_filter($uploads['opml'])) > 2) {

        $feed_urls = OPML::parse($uploads['opml']);

        foreach ($feed_urls as $url) {
            $feed = new Feed($url);
            $feed_id = $main->getState()->addFeed($feed);
            
            // Create a background job to refresh the feed (which will trigger automatic download)
            if ($feed_id > 0) {
                createRefreshJobForNewFeed($main, $feed_id);
            }
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

function createRefreshJobForNewFeed(Main $main, int $feed_id): void {
    try {
        // Create a refresh job for the new feed
        $job_id = $main->getState()->createJob('refresh_feed', $feed_id);
        
        // Start the refresh script in the background
        $cmd = sprintf(
            'cd %s && nohup /usr/local/bin/php scripts/refresh_feeds.php --feed_id=%d --job_id=%d > /dev/null 2>&1 & echo $!',
            PODSUMER_PATH,
            $feed_id,
            $job_id
        );
        
        $output = [];
        $return_var = 0;
        exec($cmd, $output, $return_var);
        
        if ($return_var === 0) {
            $pid = intval(trim($output[0] ?? '0'));
            if ($pid > 0) {
                $main->getState()->startJob($job_id, $pid);
            }
        } else {
            // If we can't start the background job, fail it
            $main->getState()->failJob($job_id, 'Failed to start background refresh process');
        }
        
    } catch (Exception $e) {
        // Log error but don't fail the entire add operation
        $main->log("Error creating refresh job for new feed $feed_id: " . $e->getMessage());
    }
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

#[Route('/process_ad_detection', 'POST', true)]
function process_ad_detection(array $args): void
{
    global $main;
    
    // Set JSON header early
    header('Content-Type: application/json');
    
    // Ensure no output before JSON
    if (ob_get_level() > 0) {
        ob_clean();
    }

    // Get item_id from JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $item_id = intval($input['item_id'] ?? 0);
    
    if (empty($item_id)) {
        echo json_encode(['error' => 'No item ID provided']);
        return;
    }
    
    // Check if ad blocking is enabled
    if (!$main->getConf('podsumer', 'ad_blocking_enabled')) {
        echo json_encode(['error' => 'Ad blocking is not enabled']);
        return;
    }

    try {
        // Create job in database
        $job_id = $main->getState()->createJob('process_ads', null, $item_id);
        
        // Start the ad detection script in the background with proper error handling
        $cmd = sprintf(
            'cd %s && nohup /usr/local/bin/php scripts/refresh_feeds.php --item_id=%d --job_id=%d > /dev/null 2>&1 & echo $!',
            PODSUMER_PATH,
            $item_id,
            $job_id
        );
        
        $output = [];
        $return_var = 0;
        exec($cmd, $output, $return_var);
        
        if ($return_var !== 0) {
            $main->getState()->failJob($job_id, 'Failed to start background process');
            echo json_encode(['error' => 'Failed to start background process']);
            return;
        }
        
        $pid = intval(trim($output[0] ?? '0'));
        if ($pid > 0) {
            $main->getState()->startJob($job_id, $pid);
        }
        
        // Return immediately
        echo json_encode(['success' => true, 'job_id' => $job_id, 'pid' => $pid]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

#[Route('/download_episode', 'POST', true)]
function download_episode(array $args): void
{
    global $main;
    
    // Get item_id from JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $item_id = intval($input['item_id'] ?? 0);
    
    header('Content-Type: application/json');
    
    if (empty($item_id)) {
        echo json_encode(['error' => 'No item ID provided']);
        return;
    }
    
    try {
        // Check if item exists
        $item = $main->getState()->getFeedItem($item_id);
        if (empty($item)) {
            echo json_encode(['error' => 'Item not found']);
            return;
        }
        
        // Check if item already has audio
        if (!empty($item['audio_file'])) {
            echo json_encode(['error' => 'Item already has audio downloaded']);
            return;
        }
        
        // Create job in database
        $job_id = $main->getState()->createJob('download_item', null, $item_id);
        
        // Start the download script in the background
        $cmd = sprintf(
            'cd %s && nohup /usr/local/bin/php scripts/refresh_feeds.php --item_id=%d --job_id=%d --download_only > /dev/null 2>&1 & echo $!',
            PODSUMER_PATH,
            $item_id,
            $job_id
        );
        
        $output = [];
        $return_var = 0;
        exec($cmd, $output, $return_var);
        
        if ($return_var !== 0) {
            $main->getState()->failJob($job_id, 'Failed to start background process');
            echo json_encode(['error' => 'Failed to start background process']);
            return;
        }
        
        $pid = intval(trim($output[0] ?? '0'));
        if ($pid > 0) {
            $main->getState()->startJob($job_id, $pid);
        }
        
        echo json_encode(['success' => true, 'job_id' => $job_id, 'pid' => $pid]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

#[Route('/refresh_all', 'POST', true)]
function refresh_all(array $args): void
{
    global $main;
    
    header('Content-Type: application/json');
    
    try {
        // Get feeds that don't already have running refresh jobs
        $feeds = $main->getState()->getFeedsWithoutRunningJobs();
        
        if (empty($feeds)) {
            echo json_encode(['error' => 'No feeds available to refresh (all feeds are already being refreshed or no feeds exist)']);
            return;
        }
        
        $job_ids = [];
        $failed_feeds = [];
        
        // Create a refresh_feed job for each feed without a running job
        foreach ($feeds as $feed) {
            try {
                $job_id = $main->getState()->createJob('refresh_feed', $feed['id']);
                
                // Start the refresh script in the background
                $cmd = sprintf(
                    'cd %s && nohup /usr/local/bin/php scripts/refresh_feeds.php --feed_id=%d --job_id=%d > /dev/null 2>&1 & echo $!',
                    PODSUMER_PATH,
                    $feed['id'],
                    $job_id
                );
                
                $output = [];
                $return_var = 0;
                exec($cmd, $output, $return_var);
                
                if ($return_var !== 0) {
                    $main->getState()->failJob($job_id, 'Failed to start background process');
                    $failed_feeds[] = $feed['name'];
                } else {
                    $pid = intval(trim($output[0] ?? '0'));
                    if ($pid > 0) {
                        $main->getState()->startJob($job_id, $pid);
                    }
                    $job_ids[] = $job_id;
                }
                
            } catch (Exception $e) {
                $failed_feeds[] = $feed['name'] . ' (' . $e->getMessage() . ')';
            }
        }
        
        $success_count = count($job_ids);
        $total_count = count($feeds);
        $failed_count = count($failed_feeds);
        
        $response = [
            'success' => true,
            'message' => "Started refresh for {$success_count} of {$total_count} available feeds",
            'job_ids' => $job_ids,
            'success_count' => $success_count,
            'total_count' => $total_count
        ];
        
        if ($failed_count > 0) {
            $response['failed_count'] = $failed_count;
            $response['failed_feeds'] = $failed_feeds;
            $response['message'] .= " ({$failed_count} failed)";
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

#[Route('/refresh_feed', 'POST', true)]
function refresh_feed(array $args): void
{
    global $main;
    
    // Get feed_id from JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $feed_id = intval($input['feed_id'] ?? 0);
    
    header('Content-Type: application/json');
    
    if (empty($feed_id)) {
        echo json_encode(['error' => 'No feed ID provided']);
        return;
    }
    
    try {
        // Create job in database
        $job_id = $main->getState()->createJob('refresh_feed', $feed_id);
        
        // Start the refresh script in the background with proper error handling
        $cmd = sprintf(
            'cd %s && nohup /usr/local/bin/php scripts/refresh_feeds.php --feed_id=%d --job_id=%d > /dev/null 2>&1 & echo $!',
            PODSUMER_PATH,
            $feed_id,
            $job_id
        );
        
        $output = [];
        $return_var = 0;
        exec($cmd, $output, $return_var);
        
        if ($return_var !== 0) {
            $main->getState()->failJob($job_id, 'Failed to start background process');
            echo json_encode(['error' => 'Failed to start background process']);
            return;
        }
        
        $pid = intval(trim($output[0] ?? '0'));
        if ($pid > 0) {
            $main->getState()->startJob($job_id, $pid);
        }
        
        // Return immediately
        echo json_encode(['success' => true, 'job_id' => $job_id, 'pid' => $pid]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

#[Route('/jobs', 'GET', true)]
function jobs(array $args): void
{
    global $main;
    
    $jobs = $main->getState()->getAllJobs(100);
    $running_jobs = $main->getState()->getRunningJobs();
    $job_stats = $main->getState()->getJobStats();
    
    $vars = [
        'jobs' => $jobs,
        'running_jobs' => $running_jobs,
        'job_stats' => $job_stats
    ];
    
    Template::render($main, 'jobs', $vars);
}

#[Route('/cancel_job', 'POST', true)]
function cancel_job(array $args): void
{
    global $main;
    
    header('Content-Type: application/json');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $job_id = intval($input['job_id'] ?? 0);
    
    if (empty($job_id)) {
        echo json_encode(['error' => 'No job ID provided']);
        return;
    }
    
    try {
        $success = $main->getState()->cancelJob($job_id);
        if ($success) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['error' => 'Failed to cancel job']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

#[Route('/job_status', 'GET', true)]
function job_status(array $args): void
{
    global $main;
    
    header('Content-Type: application/json');
    
    $job_id = intval($args['job_id'] ?? 0);
    
    if (empty($job_id)) {
        $running_jobs = $main->getState()->getRunningJobs();
        echo json_encode(['running_jobs' => $running_jobs]);
    } else {
        $job = $main->getState()->getJob($job_id);
        echo json_encode(['job' => $job]);
    }
}

#[Route('/process_all_ads', 'POST', true)]
function process_all_ads(array $args): void
{
    global $main;
    
    header('Content-Type: application/json');
    
    try {
        // Check if ad blocking is enabled
        if (!$main->getConf('podsumer', 'ad_blocking_enabled')) {
            echo json_encode(['error' => 'Ad blocking is not enabled in configuration']);
            return;
        }
        
        // Get all items that have audio files but don't have both transcript and ad_sections
        $items = $main->getState()->getItemsNeedingAdProcessing();
        
        if (empty($items)) {
            echo json_encode(['error' => 'No items found that need ad processing']);
            return;
        }
        
        $job_ids = [];
        $failed_items = [];
        
        // Create an ad processing job for each item
        foreach ($items as $item) {
            try {
                $job_id = $main->getState()->createJob('process_ads', null, $item['id']);
                
                // Start the ad processing script in the background
                $cmd = sprintf(
                    'cd %s && nohup /usr/local/bin/php scripts/refresh_feeds.php --item_id=%d --job_id=%d > /dev/null 2>&1 & echo $!',
                    PODSUMER_PATH,
                    $item['id'],
                    $job_id
                );
                
                $output = [];
                $return_var = 0;
                exec($cmd, $output, $return_var);
                
                if ($return_var !== 0) {
                    $main->getState()->failJob($job_id, 'Failed to start background process');
                    $failed_items[] = $item['name'];
                } else {
                    $pid = intval(trim($output[0] ?? '0'));
                    if ($pid > 0) {
                        $main->getState()->startJob($job_id, $pid);
                    }
                    $job_ids[] = $job_id;
                }
                
            } catch (Exception $e) {
                $failed_items[] = $item['name'] . ' (' . $e->getMessage() . ')';
            }
        }
        
        $success_count = count($job_ids);
        $total_count = count($items);
        $failed_count = count($failed_items);
        
        $response = [
            'success' => true,
            'message' => "Started ad processing for {$success_count} of {$total_count} items",
            'job_ids' => $job_ids,
            'success_count' => $success_count,
            'total_count' => $total_count
        ];
        
        if ($failed_count > 0) {
            $response['failed_count'] = $failed_count;
            $response['failed_items'] = $failed_items;
            $response['message'] .= " ({$failed_count} failed)";
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}

#[Route('/reprocess_ads', 'POST', true)]
function reprocess_ads(array $args): void
{
    global $main;
    
    header('Content-Type: application/json');
    
    if (empty($args['item_id'])) {
        echo json_encode(['error' => 'Item ID is required']);
        return;
    }
    
    $item_id = intval($args['item_id']);
    
    // Verify item exists
    $item = $main->getState()->getFeedItem($item_id);
    if (empty($item)) {
        echo json_encode(['error' => 'Item not found']);
        return;
    }
    
    // Check if item has a transcript
    $transcript = $main->getState()->getItemTranscript($item_id);
    if (empty($transcript)) {
        echo json_encode(['error' => 'Item has no transcript to reprocess. Please run ad processing first.']);
        return;
    }
    
    try {
        // Clear existing ad sections to force reprocessing
        $main->getState()->clearItemAdSections($item_id);
        $main->log("Cleared ad sections for item {$item_id} to force reprocessing");
        
        // Create a new ad detection job
        $job_id = $main->getState()->createJob('process_ads', null, $item_id);
        
        // Start the ad processing script in the background
        $cmd = sprintf(
            'cd %s && nohup /usr/local/bin/php scripts/refresh_feeds.php --item_id=%d --job_id=%d > /dev/null 2>&1 & echo $!',
            PODSUMER_PATH,
            $item_id,
            $job_id
        );
        
        $output = [];
        $return_var = 0;
        exec($cmd, $output, $return_var);
        
        if ($return_var !== 0) {
            $main->getState()->failJob($job_id, 'Failed to start background process');
            echo json_encode(['error' => 'Failed to start reprocessing job']);
            return;
        }
        
        $pid = intval(trim($output[0] ?? '0'));
        if ($pid > 0) {
            $main->getState()->startJob($job_id, $pid);
        }
        
        $main->log("Created ad detection reprocess job {$job_id} for item {$item_id} ({$item['name']})");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Ad reprocessing job started successfully',
            'job_id' => $job_id
        ]);
        
    } catch (Exception $e) {
        $main->log("Failed to create reprocess job for item {$item_id}: " . $e->getMessage());
        echo json_encode(['error' => 'Failed to create reprocess job: ' . $e->getMessage()]);
    }
}

#[Route('/segments', 'GET', true)]
function segments(array $args): void
{
    global $main;

    if (empty($args['item_id'])) {
        $main->setResponseCode(404);
        return;
    }

    $item_id = intval($args['item_id']);
    $segments = $main->getState()->getSegmentsForItem($item_id);
    $item = $main->getState()->getFeedItem($item_id);

    $vars = [
        'segments' => $segments,
        'item' => $item
    ];

    Template::render($main, 'segments', $vars);
}

#[Route('/clips', 'GET', true)]
function clips(array $args): void
{
    global $main;

    if (empty($args['segment_id'])) {
        $main->setResponseCode(404);
        return;
    }

    $segment_id = intval($args['segment_id']);
    $clips = $main->getState()->getClipsForSegment($segment_id);
    $segment = $main->getState()->getSegment($segment_id);

    $vars = [
        'clips' => $clips,
        'segment' => $segment
    ];

    Template::render($main, 'clips', $vars);
}

#[Route('/delete_segment', 'GET', true)]
function delete_segment(array $args): void
{
    global $main;

    if (empty($args['segment_id'])) {
        $main->setResponseCode(404);
        return;
    }

    $segment_id = intval($args['segment_id']);
    $segment = $main->getState()->getSegment($segment_id);
    if (!empty($segment)) {
        $main->getState()->deleteSegment($segment_id);
        $main->redirect('/segments?item_id=' . $segment['item_id']);
    } else {
        $main->setResponseCode(404);
    }
}

#[Route('/delete_clip', 'GET', true)]
function delete_clip(array $args): void
{
    global $main;

    if (empty($args['clip_id'])) {
        $main->setResponseCode(404);
        return;
    }

    $clip_id = intval($args['clip_id']);
    $main->getState()->deleteClip($clip_id);
    $main->redirect($_SERVER['HTTP_REFERER'] ?? '/');
}

