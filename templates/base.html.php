<!DOCTYPE html>
<html lang="<?= $LANGUAGE ?>">
<head>
    <title><?= $PAGE_TITLE ?: 'Podsumer' ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-neutral-900 text-neutral-100 font-sans">
    <div class="container mx-auto p-10">
        <h1 class="text-m font-black text-right">
            <a href="/">Feeds</a>
            &nbsp;|&nbsp;
            <a href="/episodes">Episodes</a>
            &nbsp;|&nbsp;
            <a href="/opml">OPML</a>
            &nbsp;|&nbsp;
            <?php
            $running_jobs = $this->main->getState()->getRunningJobs();
            $job_count = count($running_jobs);
            if ($job_count > 0) {
                echo '<a href="/jobs">Jobs (' . $job_count . ')</a>';
            } else {
                echo '<a href="/jobs">Jobs</a>';
            }
            ?>
            &nbsp;|&nbsp;
            <a href="#" onclick="refreshAllFeeds(); return false;">Refresh All</a>
            &nbsp;|&nbsp;
            <a href="#" onclick="processAllAds(); return false;">Process All</a>
            &nbsp;|&nbsp;
           <?= round($db_size/1024/1024/1024, 2) ?> GB
        </h1>
        <? include($BODY) ?>
    </div>
    <div class="text-center py-8 text-s text-neutral-500">
        <p>
            Thank You for Listening With Podsumer
            <br>
            <span class="text-xs">
                If you find this open source project of value please consider
                <a target="_blank" rel="noreferrer" href="https://github.com/sponsors/joshwbrick" class="text-green-700 underline">sponsoring</a>
                or <a target="_blank" rel="noreferrer" href="https://github.com/joshwbrick/podsumer" class="text-amber-700 underline">contributing to</a>
                further development.
            </span>
        </p>
        <p class="text-xs">
            <br>
            Released under the MIT License &ndash; Database version: <?= $this->main->getState()->getVersion(); ?>
        </p>
    </div>
    <script>
    function refreshAllFeeds() {
        if (!confirm('Refresh all feeds? This will run in the background.')) {
            return;
        }
        
        fetch('/refresh_all', {
            method: 'POST'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message || 'Feed refresh started in the background.');
                location.reload(); // Reload to show job status
            } else {
                alert('Error starting feed refresh: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Error starting feed refresh: ' + err.message);
            console.error('Fetch error:', err);
        });
    }
    
    function processAllAds() {
        if (!confirm('Process ads for the newest episode from each feed? This will transcribe and detect ads only for the most recent episode in each feed that hasn\'t been processed yet. Ad-free episodes will not be reprocessed. This may take a long time and cost money.')) {
            return;
        }
        
        fetch('/process_all_ads', {
            method: 'POST'
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                alert(data.message || 'Ad processing started in the background.');
                location.reload(); // Reload to show job status
            } else {
                alert('Error starting ad processing: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            alert('Error starting ad processing: ' + err.message);
            console.error('Fetch error:', err);
        });
    }
    </script>
</body>
</html>
