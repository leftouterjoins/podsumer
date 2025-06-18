#!/usr/bin/env php
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
use Brickner\Podsumer\Main;
use Brickner\Podsumer\AdDetection;
use Brickner\Podsumer\File;

# Create the application.
$main = new Main(PODSUMER_PATH, array_merge($_SERVER, $_ENV), [], []);

# Parse command line arguments
$options = getopt('', ['feed_id:', 'item_id:', 'job_id:', 'download_only']);
$feed_id = isset($options['feed_id']) ? intval($options['feed_id']) : null;
$item_id = isset($options['item_id']) ? intval($options['item_id']) : null;
$job_id = isset($options['job_id']) ? intval($options['job_id']) : null;
$download_only = isset($options['download_only']);

$openai_cost = 0.0;

function logMessage(Main $main, ?int $job_id, string $message): void {
    echo $message . "\n";
    if ($job_id) {
        $main->getState()->updateJobLog($job_id, $message);
    }
}

function logError(Main $main, ?int $job_id, string $error): void {
    $message = "ERROR: " . $error;
    echo $message . "\n";
    if ($job_id) {
        $main->getState()->updateJobLog($job_id, $message);
    }
}

// Set up error handlers to capture all errors in job log
if ($job_id) {
    // Capture PHP errors, warnings, notices
    set_error_handler(function($severity, $message, $file, $line) use ($main, $job_id) {
        $error_types = [
            E_ERROR => 'FATAL ERROR',
            E_WARNING => 'WARNING',
            E_NOTICE => 'NOTICE',
            E_USER_ERROR => 'USER ERROR',
            E_USER_WARNING => 'USER WARNING',
            E_USER_NOTICE => 'USER NOTICE',
            E_STRICT => 'STRICT',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
            E_DEPRECATED => 'DEPRECATED',
            E_USER_DEPRECATED => 'USER DEPRECATED'
        ];
        
        $error_type = $error_types[$severity] ?? 'UNKNOWN ERROR';
        $error_msg = "$error_type: $message in $file on line $line";
        logError($main, $job_id, $error_msg);
        
        // Don't execute PHP internal error handler
        return true;
    });
    
    // Capture fatal errors and exceptions
    register_shutdown_function(function() use ($main, $job_id) {
        $error = error_get_last();
        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $error_msg = "FATAL ERROR: {$error['message']} in {$error['file']} on line {$error['line']}";
            logError($main, $job_id, $error_msg);
            
            // Mark job as failed
            try {
                $main->getState()->failJob($job_id, $error_msg);
            } catch (Exception $e) {
                // If we can't even log the failure, write to error log as last resort
                error_log("Failed to log job failure: " . $e->getMessage());
            }
        }
    });
    
    // Capture uncaught exceptions
    set_exception_handler(function($exception) use ($main, $job_id) {
        $error_msg = "UNCAUGHT EXCEPTION: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
        $error_msg .= "\nStack trace:\n" . $exception->getTraceAsString();
        logError($main, $job_id, $error_msg);
        
        // Mark job as failed
        try {
            $main->getState()->failJob($job_id, $error_msg);
        } catch (Exception $e) {
            error_log("Failed to log job failure: " . $e->getMessage());
        }
        
        exit(1);
    });
}

function downloadItemAudio(Main $main, int $item_id, ?int $job_id = null): bool {
    try {
        $item = $main->getState()->getFeedItem($item_id);
        if (empty($item)) {
            logError($main, $job_id, "Item not found: $item_id");
            return false;
        }
        
        if (!empty($item['audio_file'])) {
            logMessage($main, $job_id, "Item already has audio downloaded: " . $item['name']);
            return true;
        }
        
        if (empty($item['audio_url'])) {
            logError($main, $job_id, "No audio URL available for item: " . $item['name']);
            return false;
        }
        
        logMessage($main, $job_id, "Downloading audio for: " . $item['name']);
        
        $feed = $main->getState()->getFeedForItem($item_id);
        $file = new File($main);
        $file_id = $file->cacheUrl($item['audio_url'], $feed);
        
        $main->getState()->setItemAudioFile($item_id, $file_id);
        
        logMessage($main, $job_id, "Successfully downloaded audio for: " . $item['name']);
        return true;
        
    } catch (Exception $e) {
        logError($main, $job_id, "Error downloading audio for item $item_id: " . $e->getMessage());
        return false;
    }
}

function getItemsToDownload(Main $main, int $feed_id, ?int $job_id = null): array {
    $items = $main->getState()->getFeedItems($feed_id);
    
    if (empty($items)) {
        logMessage($main, $job_id, "No items found for feed $feed_id");
        return [];
    }
    
    // Find items that have audio downloaded
    $downloaded_items = array_filter($items, function($item) {
        return !empty($item['audio_file']);
    });
    
    if (empty($downloaded_items)) {
        // No items downloaded - download the first (newest) episode
        logMessage($main, $job_id, "No episodes downloaded, will download first episode");
        return [$items[0]];
    }
    
    // Find the newest downloaded item by published date
    $newest_downloaded = null;
    $newest_downloaded_time = null;
    
    foreach ($downloaded_items as $item) {
        $item_time = strtotime($item['published']);
        if ($newest_downloaded_time === null || $item_time > $newest_downloaded_time) {
            $newest_downloaded = $item;
            $newest_downloaded_time = $item_time;
        }
    }
    
    // Find all items newer than the newest downloaded item
    $items_to_download = [];
    foreach ($items as $item) {
        $item_time = strtotime($item['published']);
        if (empty($item['audio_file']) && $item_time > $newest_downloaded_time) {
            $items_to_download[] = $item;
        }
    }
    
    if (empty($items_to_download)) {
        logMessage($main, $job_id, "No new episodes to download");
    } else {
        logMessage($main, $job_id, "Found " . count($items_to_download) . " new episodes to download");
    }
    
    return $items_to_download;
}

function detectIfNewFeed(Main $main, int $feed_id, ?int $job_id = null): bool {
    $items = $main->getState()->getFeedItems($feed_id);
    
    if (empty($items)) {
        return true; // No items means new feed
    }
    
    // Check if ANY item has been downloaded
    foreach ($items as $item) {
        if (!empty($item['audio_file'])) {
            return false; // Found a downloaded item, not a new feed
        }
    }
    
    return true; // No downloaded items, treat as new feed
}

try {
    if ($job_id) {
        logMessage($main, $job_id, "Job started with ID: $job_id");
        if ($feed_id) {
            logMessage($main, $job_id, "Processing feed ID: $feed_id");
        }
        if ($item_id) {
            logMessage($main, $job_id, "Processing item ID: $item_id");
        }
    }

    # Handle download-only for specific item
    if ($item_id !== null && $download_only) {
        logMessage($main, $job_id, "Starting download for item $item_id");
        
        $success = downloadItemAudio($main, $item_id, $job_id);
        
        if ($success) {
            logMessage($main, $job_id, "Download completed successfully");
            if ($job_id) {
                $main->getState()->completeJob($job_id, 0);
            }
        } else {
            throw new Exception("Failed to download item $item_id");
        }
        
        exit(0);
    }

    # Handle ad detection for specific item
    if ($item_id !== null) {
        logMessage($main, $job_id, "Starting ad detection for item $item_id");
        
        $item = $main->getState()->getFeedItem($item_id);
        if (empty($item)) {
            throw new Exception("Item not found: $item_id");
        }
        
        logMessage($main, $job_id, "Found item: " . $item['name']);
        
        if (empty($item['audio_file'])) {
            throw new Exception("No audio file available for item: $item_id");
        }
        
        logMessage($main, $job_id, "Audio file available, starting ad detection");
        
        $adDetection = new AdDetection($main);
        
        # Get the audio file
        $file_data = $main->getState()->getFileById($item['audio_file']);
        
        logMessage($main, $job_id, "Retrieved audio file data");
        
        # Save audio to temporary file for processing
        $temp_file = tempnam(sys_get_temp_dir(), 'podsumer_audio_');
        
        # Determine file extension from mimetype
        $mimetype = explode(';', $file_data['mimetype'])[0];
        $extension = match($mimetype) {
            'audio/mpeg', 'audio/mp3' => '.mp3',
            'audio/mp4' => '.mp4',
            'audio/m4a', 'audio/x-m4a' => '.m4a',
            'audio/wav', 'audio/x-wav' => '.wav',
            'audio/ogg' => '.ogg',
            'audio/flac' => '.flac',
            'audio/webm' => '.webm',
            default => '.mp3'
        };
        
        $audio_file_path = $temp_file . $extension;
        rename($temp_file, $audio_file_path);
        
        logMessage($main, $job_id, "Created temporary audio file: $audio_file_path");
        
        file_put_contents($audio_file_path, $file_data['data']);
        
        logMessage($main, $job_id, "Written audio data to temporary file");
        
        # Process ads
        $cost = $adDetection->processItem($item_id, $audio_file_path);
        $openai_cost += $cost;
        
        logMessage($main, $job_id, "Ad detection completed. Cost: $" . number_format($cost, 4));
        
        # Clean up temporary file
        unlink($audio_file_path);
        
        logMessage($main, $job_id, "Cleaned up temporary file");
        
        if ($job_id) {
            $main->getState()->completeJob($job_id, $openai_cost);
            logMessage($main, $job_id, "Job completed successfully");
        }
        
        exit(0);
    }

    # Handle feed refresh
    if ($feed_id !== null) {
        $feed = $main->getState()->getFeed($feed_id);
        if ($feed) {
            $feeds = [$feed];
            logMessage($main, $job_id, "Processing single feed: " . $feed['name']);
        } else {
            throw new Exception("Feed not found: $feed_id");
        }
    } else {
        $feeds = $main->getState()->getFeeds();
        logMessage($main, $job_id, "Processing all feeds: " . count($feeds) . " feeds found");
    }

    $total_feeds = count($feeds);
    $processed_feeds = 0;

    foreach ($feeds as $feed) {
        try {
            logMessage($main, $job_id, "Processing feed: " . $feed['name'] . " (ID: " . $feed['id'] . ")");
            

            
            # Refresh feed
            logMessage($main, $job_id, "Creating Feed object for URL: " . $feed['url']);
            $refresh_feed = new Feed($feed['url']);
            $refresh_feed->setFeedId($feed['id']);
            
            logMessage($main, $job_id, "Adding feed to database");
            $main->getState()->addFeed($refresh_feed);
            
            logMessage($main, $job_id, "Feed refresh completed for: " . $feed['name']);
            
            # Automatic downloading logic
            $is_new_feed = detectIfNewFeed($main, $feed['id'], $job_id);
            
            if ($is_new_feed) {
                logMessage($main, $job_id, "New feed detected, will download first episode");
                $items = $main->getState()->getFeedItems($feed['id']);
                if (!empty($items)) {
                    $success = downloadItemAudio($main, $items[0]['id'], $job_id);
                    if ($success) {
                        logMessage($main, $job_id, "Successfully downloaded first episode for new feed");
                    } else {
                        logMessage($main, $job_id, "Failed to download first episode for new feed");
                    }
                } else {
                    logMessage($main, $job_id, "No items found for new feed");
                }
            } else {
                logMessage($main, $job_id, "Existing feed, checking for new episodes to download");
                $items_to_download = getItemsToDownload($main, $feed['id'], $job_id);
                
                if (!empty($items_to_download)) {
                    $download_count = 0;
                    foreach ($items_to_download as $item) {
                        $success = downloadItemAudio($main, $item['id'], $job_id);
                        if ($success) {
                            $download_count++;
                        }
                    }
                    logMessage($main, $job_id, "Successfully downloaded $download_count out of " . count($items_to_download) . " new episodes");
                } else {
                    logMessage($main, $job_id, "No new episodes to download");
                }
            }
            
            logMessage($main, $job_id, "Feed refresh and download completed, ad processing will be handled separately");
            
            $processed_feeds++;
            logMessage($main, $job_id, "Completed processing feed: " . $feed['name'] . " ($processed_feeds/$total_feeds)");
            
        } catch (Exception $e) {
            $error_msg = "Error processing feed " . $feed['name'] . ": " . $e->getMessage();
            logError($main, $job_id, $error_msg);
            
            if ($job_id) {
                $main->getState()->failJob($job_id, $error_msg);
            }
            continue;
        }
    }

    if ($job_id) {
        $main->getState()->completeJob($job_id, $openai_cost);
        logMessage($main, $job_id, "All feeds processed successfully. Total cost: $" . number_format($openai_cost, 4));
    }

} catch (Exception $e) {
    $error_msg = "Fatal error: " . $e->getMessage();
    logError($main, $job_id, $error_msg);
    
    if ($job_id) {
        $main->getState()->failJob($job_id, $error_msg);
    }
    
    exit(1);
} 