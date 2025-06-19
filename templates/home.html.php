<div class="container py-10">

    <? if (empty($feeds)): ?>
    <div class="container py-10 clear">
        <h1 class="text-2xl">No Feeds</h1>
    </div>

    <? else: ?>
    <? foreach ($feeds as $feed): ?>
    <div class="container py-10 clear-left">
        <a href="/feed?id=<?= $feed['id'] ?>">
            <img src="/image?feed_id=<?= $feed['id'] ?>" class="float-left w-48 pr-5">
        </a>

        <h1 class="text-2xl pb-2">
            <a href="/feed?id=<?= $feed['id'] ?>">
            <?= $feed['name'] ?>
            </a>
        </h1>
        <p>
            <span class="text-neutral-400 text-s pb-4 font-bold">
                <?= \date('m/d/Y', strtotime($feed['last_update'])) ?>
                &nbsp;|&nbsp;
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
                &nbsp;|&nbsp;
                <a href="/delete_feed?feed_id=<?= $feed['id'] ?>">Delete</a>
            </span>
            <br>
            <?= $feed['description'] ?>
        </p>
    </div>
    <? endforeach ?>
    <? endif ?>

    <form method="POST" action="/add" enctype="multipart/form-data" class="clear-left py-10">
        <h1 class="text-2xl">Add Feed(s)</h1>
        Upload OPML: <input type="file" name="opml" class="text-white font-bold py-2 px-4 rounded inline">
        <input type="text" class="text-black inline w-1/2" name="url" placeholder="Feed URL">
        &nbsp;&nbsp;
        <input type="submit" class="bg-neutral-500 text-white font-bold py-2 px-4 rounded">
    </form>

    <form method="GET" action="/search" class="clear-left py-10">
        <h1 class="text-2xl">Search PodcastIndex</h1>
        <input type="text" class="text-black inline w-1/2" name="q" placeholder="Search term">
        &nbsp;&nbsp;
        <input type="submit" class="bg-neutral-500 text-white font-bold py-2 px-4 rounded">
    </form>
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
</script>

