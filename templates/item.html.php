<div class="container py-10 content-center clear">

    <h1 class="py-5 text-2xl">
        <a href="/feed?id=<?= $item['feed_id'] ?>"><?= $feed['name'] ?></a>
    </h1>

    <h1 class="text-xl pb-5"><?= $item['name'] ?></h1>

    <div class="media-container">
        <img src="/image?<?= 'item_id='.$item['id'] ?: 'feed_id'.$feed['id'] ?>" class="album-art">
        <audio autoplay controls src="/audio?item_id=<?= $item['id'] ?>" class="player"></audio>
    </div>

    <div id="item-desc">
        <?php
        $description = $item['description'];
        // Check if description contains HTML tags
        if (strip_tags($description) === $description) {
            // No HTML tags found, wrap in pre tag
            echo '<pre>' . htmlspecialchars($description) . '</pre>';
        } else {
            // HTML tags found, display as-is
            echo $description;
        }
        ?>
    </div>

    <?php 
    // Display ad segments if ad blocking is enabled and segments exist
    if ($this->main->getConf('podsumer', 'ad_blocking_enabled')) {
        $adSectionsData = [];
        if (!empty($item['ad_sections'])) {
            if (is_string($item['ad_sections'])) {
                $adSectionsData = json_decode($item['ad_sections'], true) ?: [];
            } else {
                $adSectionsData = $item['ad_sections'];
            }
        }
        
        if (!empty($adSectionsData) && is_array($adSectionsData)) {
            ?>
            <div class="ad-segments-container">
                <h3 class="ad-segments-title">Ad Segments Detected</h3>
                <table class="w-full">
                    <thead>
                        <tr class="text-left">
                            <th class="py-3 pl-4 text-base font-bold w-12">#</th>
                            <th class="py-3 pl-4 text-base font-bold w-36">Time Range</th>
                            <th class="py-3 pl-4 text-base font-bold w-20">Duration</th>
                            <th class="py-3 pl-4 text-base font-bold">Summary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adSectionsData as $index => $segment): ?>
                            <?php 
                            // Format time with hours if needed
                            $formatTime = function($seconds) {
                                $totalSeconds = intval($seconds);
                                $hours = intval($totalSeconds / 3600);
                                $minutes = intval(($totalSeconds % 3600) / 60);
                                $secs = $totalSeconds % 60;
                                
                                if ($hours > 0) {
                                    return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
                                } else {
                                    return sprintf('%d:%02d', $minutes, $secs);
                                }
                            };
                            
                            $startTime = $formatTime($segment['start']);
                            $endTime = $formatTime($segment['end']);
                            $duration = $segment['end'] - $segment['start'];
                            $durationFormatted = $formatTime($duration);
                            $reason = isset($segment['reason']) ? htmlspecialchars($segment['reason']) : '';
                            ?>
                            <tr class="<?= $index % 2 === 1 ? 'bg-neutral-800' : '' ?>">
                                <td class="py-3 pl-4 text-base w-12">
                                    <?= $index + 1 ?>.
                                </td>
                                <td class="py-3 pl-4 text-sm font-mono whitespace-nowrap w-36">
                                    <?= $startTime ?> - <?= $endTime ?>
                                </td>
                                <td class="py-3 pl-4 text-sm text-neutral-400 whitespace-nowrap w-20">
                                    <?= $durationFormatted ?>
                                </td>
                                <td class="py-3 pl-4 text-base text-neutral-300">
                                    <?= $reason ?: '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    }
    ?>

    <?php
    // Display transcript if available
    if (!empty($item['transcript'])) {
        $transcriptData = json_decode($item['transcript'], true);
        if ($transcriptData && isset($transcriptData['segments']) && is_array($transcriptData['segments'])) {
            ?>
            <div class="transcript-container">
                <h3 class="transcript-title">Transcript</h3>
                <div class="transcript-content">
                    <?php foreach ($transcriptData['segments'] as $segment): ?>
                        <?php 
                        $start = floatval($segment['start'] ?? 0);
                        $end = floatval($segment['end'] ?? 0);
                        $text = isset($segment['text']) && is_string($segment['text']) ? trim($segment['text']) : '';
                        
                        if (!empty($text)) {
                            // Format time with hours if needed
                            $formatTime = function($seconds) {
                                $totalSeconds = intval($seconds);
                                $hours = intval($totalSeconds / 3600);
                                $minutes = intval(($totalSeconds % 3600) / 60);
                                $secs = $totalSeconds % 60;
                                
                                if ($hours > 0) {
                                    return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
                                } else {
                                    return sprintf('%d:%02d', $minutes, $secs);
                                }
                            };
                            
                            $startTime = $formatTime($start);
                        ?>
                        <div class="transcript-segment" data-start="<?= $start ?>" data-end="<?= $end ?>">
                            <span class="transcript-timestamp"><?= $startTime ?></span>
                            <span class="transcript-text"><?= htmlspecialchars($text) ?></span>
                        </div>
                        <?php } ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php
        }
    }
    ?>

</div>

<style type="text/css">
   #item-desc {
       max-width: 65ch;
       margin: 0 auto;
       line-height: 1.6;
       font-size: 1.05rem;
   }
   #item-desc a {
       color: rgb(253, 230, 138);
       text-decoration: underline;
   }
   #item-desc p {
       margin-bottom: 1em;
   }
   #item-desc pre {
       white-space: pre-wrap;
       word-wrap: break-word;
       font-family: inherit;
       font-size: inherit;
       line-height: inherit;
       margin: 0;
       padding: 0;
   }

   .media-container {
       max-width: 380px;
       margin: 0 auto 2rem;
       border-radius: 8px;
       box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
       overflow: hidden; /* ensure rounded corners apply to children */
   }

   .album-art {
       display: block;
       width: 100%;
       height: auto;
   }

   .player {
       width: 100%;
       margin: 0;
       border: none;
       background-color: #1f2937; /* neutral-800 */
   }

   /* Darker controls */
   .player::-webkit-media-controls-panel {
       background-color: #1f2937;
       color: rgb(253, 230, 138);
   }

   @media (max-width: 640px) {
       .media-container {
           max-width: 90%;
       }
   }

   /* Ad segments styling */
   .ad-segments-container {
       max-width: 65ch;
       margin: 2rem auto;
   }

   .ad-segments-title {
       font-size: 1.25rem;
       font-weight: 600;
       margin: 0 0 1rem 0;
       text-align: left;
   }

   @media (max-width: 640px) {
       .ad-segments-container {
           max-width: 90%;
           margin: 2rem auto;
       }
   }

   /* Transcript styling */
   .transcript-container {
       max-width: 65ch;
       margin: 2rem auto;
   }

   .transcript-title {
       font-size: 1.25rem;
       font-weight: 600;
       margin: 0 0 1rem 0;
       text-align: left;
   }

   .transcript-content {
       max-height: 400px;
       overflow-y: auto;
       padding: 1rem;
   }

   .transcript-segment {
       margin-bottom: 0.75rem;
       padding: 0.5rem;
       cursor: pointer;
       transition: opacity 0.2s ease;
       display: flex;
       align-items: flex-start;
   }

   .transcript-segment:hover {
       opacity: 0.8;
   }

   .transcript-timestamp {
       font-family: monospace;
       font-size: 0.875rem;
       color: #9ca3af;
       margin-right: 0.75rem;
       min-width: 4rem;
       flex-shrink: 0;
   }

   .transcript-text {
       color: #e5e7eb;
       line-height: 1.5;
       flex: 1;
   }

   @media (max-width: 640px) {
       .transcript-container {
           max-width: 90%;
           margin: 2rem auto;
       }
       
       .transcript-content {
           max-height: 300px;
           padding: 0.75rem;
       }
       
       .transcript-segment {
           padding: 0.5rem 0.25rem;
       }
       
       .transcript-timestamp {
           display: block;
           margin-bottom: 0.25rem;
           margin-right: 0;
           text-align: left;
           min-width: auto;
       }
   }
</style>
<script type="text/javascript">
    (function() {
        const audio = document.querySelector('audio');
        const itemId = <?= $item['id'] ?>;
        const interval = (<?= $this->main->getConf('podsumer', 'playback_interval') ?? 5 ?>) * 1000;
        const rewind = <?= $this->main->getConf('podsumer', 'playback_rewind') ?? 5 ?>;
        
        // Ad sections data - decode JSON if it's a string
        <?php 
        $adSectionsData = [];
        if (!empty($item['ad_sections'])) {
            if (is_string($item['ad_sections'])) {
                $adSectionsData = json_decode($item['ad_sections'], true) ?: [];
            } else {
                $adSectionsData = $item['ad_sections'];
            }
        }
        ?>
        const adSections = <?= json_encode($adSectionsData) ?>;
        const adBlockingEnabled = <?= $this->main->getConf('podsumer', 'ad_blocking_enabled') ? 'true' : 'false' ?>;
        const useFfmpeg = <?= $this->main->getConf('podsumer', 'use_ffmpeg_ad_removal') ? 'true' : 'false' ?>;
        
        // Skip ads during playback if ad blocking is enabled and ffmpeg is not used
        if (adBlockingEnabled && !useFfmpeg && Array.isArray(adSections) && adSections.length > 0) {
            audio.addEventListener('timeupdate', function() {
                const currentTime = audio.currentTime;
                
                // Check if we're in an ad section
                for (const ad of adSections) {
                    if (currentTime >= ad.start && currentTime < ad.end) {
                        // Skip to the end of the ad
                        audio.currentTime = ad.end;
                        console.log('Skipped ad section from', ad.start, 'to', ad.end);
                        break;
                    }
                }
            });
        }

        fetch('/get_playback?item_id=' + itemId)
            .then(r => r.json())
            .then(d => {
                if (d.position > 0) {
                    audio.currentTime = Math.max(0, d.position - rewind);
                }
            });

        setInterval(() => {
            if (!audio.paused) {
                const params = new URLSearchParams();
                params.append('item_id', itemId);
                params.append('position', Math.floor(audio.currentTime));
                fetch('/set_playback', {method: 'POST', body: params});
            }
        }, interval);

        // Convert bare URLs inside #item-desc to clickable links that open in new tab
        (function linkifyDesc() {
            const container = document.getElementById('item-desc');
            if (!container) return;

            const urlRegex = /https?:\/\/[^\s]+/g;

            function linkifyNode(node) {
                // Process only text nodes
                if (node.nodeType === Node.TEXT_NODE) {
                    const text = node.textContent;
                    if (!text) return;
                    if (urlRegex.test(text)) {
                        const span = document.createElement('span');
                        span.innerHTML = text.replace(urlRegex, url => `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`);
                        node.replaceWith(...span.childNodes);
                    }
                } else if (node.nodeType === Node.ELEMENT_NODE && node.tagName !== 'A') {
                    // Recurse into child nodes but skip existing anchors
                    Array.from(node.childNodes).forEach(linkifyNode);
                }
            }

            Array.from(container.childNodes).forEach(linkifyNode);
        })();

        // Ensure any existing links open in a new window/tab
        document.querySelectorAll('#item-desc a').forEach(link => {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        });

        // Make transcript segments clickable to jump to specific times
        document.querySelectorAll('.transcript-segment').forEach(segment => {
            segment.addEventListener('click', function() {
                const startTime = parseFloat(this.getAttribute('data-start'));
                if (!isNaN(startTime) && audio) {
                    audio.currentTime = startTime;
                    // Scroll audio player into view
                    audio.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        });
    })();


</script>
