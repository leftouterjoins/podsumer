<div class="container py-10">
    <h1 class="text-2xl py-4"><?= $feed['name'] ?></h1>

    <p class="py-4"><?= $feed['description'] ?></p>

    <p class="font-bold py-4">
        <a href="/rss?feed_id=<?= $feed['id'] ?>">RSS</a>
        &nbsp;|&nbsp;
        <?php
        $running_job = $this->main->getState()->getRunningJobForFeed($feed['id']);
        if ($running_job):
        ?>
        <a href="/jobs" class="text-yellow-400">Refreshing (Job #<?= $running_job['id'] ?>)</a>
        <?php else: ?>
        <a href="#" onclick="refreshFeed(<?= $feed['id'] ?>); return false;">Refresh</a>
        <?php endif; ?>
    </p>

    <? foreach ($items as $id => $item): ?>
    <div class="w-full clear-left py-8">
        <a href="/item?item_id=<?= $item['id'] ?>">
            <img src="/image?<?= 'item_id='.$item['id'] ?: 'feed_id='.$feed['id'] ?>" class="w-32 border-solid border-neutral-800 border inline float-left mr-4">
        </a>
        <a href="/item?item_id=<?= $item['id'] ?>" class="text-xl">
            <?= $item['name'] ?>
        </a>
        <br>
        <span class="text-neutral-500">
        <?= round($item['size'] / 1024 / 1024, 1) ?>MB
        &nbsp;|&nbsp;
        <?= date('m/d/Y', strtotime($item['published'])); ?>
        <?php
        // Check for any running job for this item (needed for both download and ad processing)
        $running_job = $this->main->getState()->getRunningJobForItem($item['id']);
        ?>
        <? if (!empty($item['audio_file'])) { ?>
        &nbsp;|&nbsp;
        <a href="/delete_audio?item_id=<?= $item['id'] ?>">Delete Audio</a>
        <? 
        // Check if ad processing has been completed (ad_sections is not null/empty string)
        $adProcessingCompleted = false;
        $hasAdSections = false;
        $ad_sections_raw = $item['ad_sections'] ?? null;
        
        if ($ad_sections_raw !== null && $ad_sections_raw !== '') {
            $adProcessingCompleted = true;
            if (is_string($ad_sections_raw)) {
                $adData = json_decode($ad_sections_raw, true);
                $hasAdSections = !empty($adData);
            } else {
                $hasAdSections = true;
            }
        }
        ?>
        <? if ($this->main->getConf('podsumer', 'ad_blocking_enabled') && !$adProcessingCompleted) { ?>
        &nbsp;|&nbsp;
        <?php if ($running_job && $running_job['type'] === 'process_ads'): ?>
        <a href="/jobs" class="text-yellow-400">Processing Ads (Job #<?= $running_job['id'] ?>)</a>
        <?php else: ?>
        <a href="#" onclick="processAds(<?= $item['id'] ?>); return false;">Process Ads</a>
        <?php endif; ?>
        <? } elseif ($hasAdSections) { ?>
        &nbsp;|&nbsp;
        <span class="text-green-500">✓ Ads Detected</span>
        &nbsp;|&nbsp;
        <a href="#" onclick="reprocessAds(<?= $item['id'] ?>); return false;" class="text-yellow-500">Reprocess</a>
        <? } ?>
        <? } else { ?>
        &nbsp;|&nbsp;
        <?php if ($running_job && $running_job['type'] === 'download_item'): ?>
        <a href="/jobs" class="text-yellow-400">Downloading (Job #<?= $running_job['id'] ?>)</a>
        <?php else: ?>
        <a href="#" onclick="downloadEpisode(<?= $item['id'] ?>); return false;">Download</a>
        <?php endif; ?>
        <? } ?>
        </span>
        <br>
        <?= substr(strip_tags($item['description']), 0, 360); ?>
    </div>
    <? endforeach ?>
    <div class="py-4 font-bold">
        <? if ($page > 1) { ?>
            <a href="/feed?id=<?= $feed['id'] ?>&page=<?= $page - 1 ?>">Previous</a>
        <? } ?>
        <? if ($page < $page_count) { ?>
            <? if ($page > 1) { ?>&nbsp;|&nbsp;<? } ?>
            <a href="/feed?id=<?= $feed['id'] ?>&page=<?= $page + 1 ?>">Next</a>
        <? } ?>
        <span>&nbsp;&nbsp;Page <?= $page ?> of <?= $page_count ?></span>
    </div>
</div>

<script type="text/javascript">
function refreshFeed(feedId) {
    if (!confirm('Refresh this feed? This will run in the background.')) {
        return;
    }
    
    const link = event.target;
    link.textContent = 'Refreshing...';
    link.style.pointerEvents = 'none';
    
    fetch('/refresh_feed', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ feed_id: feedId })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        link.textContent = 'Refresh';
        link.style.pointerEvents = 'auto';
        if (data.success) {
            alert('Feed refresh started in the background.');
            location.reload(); // Reload to show job status
        } else {
            alert('Error starting feed refresh: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        link.textContent = 'Refresh';
        link.style.pointerEvents = 'auto';
        alert('Error starting feed refresh: ' + err.message);
        console.error('Fetch error:', err);
    });
}

function processAds(itemId) {
    if (!confirm('Process ad detection for this episode? This will use your OpenAI API credits and run in the background.')) {
        return;
    }
    
    const link = event.target;
    link.textContent = 'Processing...';
    link.style.pointerEvents = 'none';
    
    fetch('/process_ad_detection', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ item_id: itemId })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        link.textContent = 'Process Ads';
        link.style.pointerEvents = 'auto';
        if (data.success) {
            alert('Ad detection started in the background.');
            location.reload(); // Reload to show job status
        } else {
            alert('Error starting ad detection: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        link.textContent = 'Process Ads';
        link.style.pointerEvents = 'auto';
        alert('Error starting ad detection: ' + err.message);
        console.error('Fetch error:', err);
    });
}

function downloadEpisode(itemId) {
    if (!confirm('Download this episode? This will run in the background.')) {
        return;
    }
    
    const link = event.target;
    link.textContent = 'Downloading...';
    link.style.pointerEvents = 'none';
    
    fetch('/download_episode', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ item_id: itemId })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        link.textContent = 'Download';
        link.style.pointerEvents = 'auto';
        if (data.success) {
            alert('Episode download started in the background.');
            location.reload(); // Reload to show job status
        } else {
            alert('Error starting download: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(err => {
        link.textContent = 'Download';
        link.style.pointerEvents = 'auto';
        alert('Error starting download: ' + err.message);
        console.error('Fetch error:', err);
    });
}

function reprocessAds(itemId) {
    if (!confirm('Are you sure you want to reprocess ads for this item? This will clear existing ad detection results and reprocess with the current model.')) {
        return;
    }

    const link = event.target;
    const originalText = link.textContent;
    link.textContent = 'Processing...';
    link.style.pointerEvents = 'none';

    const formData = new FormData();
    formData.append('item_id', itemId);

    fetch('/reprocess_ads', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Ad reprocessing job started successfully. Check the Jobs page to monitor progress.');
            location.reload(); // Reload to show updated status
        } else {
            alert('Error: ' + (data.error || 'Failed to start reprocessing'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Network error occurred while starting reprocessing job');
    })
    .finally(() => {
        link.textContent = originalText;
        link.style.pointerEvents = 'auto';
    });
}
</script>
