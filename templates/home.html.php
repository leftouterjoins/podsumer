<div class="container py-10">

    <? if (empty($feeds)): ?>
    <div class="container py-10 clear">
        <h1 class="text-2xl">No Feeds</h1>
    </div>

    <? else: ?>
    <? foreach ($feeds as $feed): ?>
    <div class="container py-10 clear-left">
        <a href="/feed?id=<?= $feed['id'] ?>">
            <img src="/file?hash=<?= $feed['image'] ?>" class="float-left w-48 pr-5">
        </a>

        <h1 class="text-3xl pb-2">
            <a href="/feed?id=<?= $feed['id'] ?>">
            <?= $feed['name'] ?>
            </a>
        </h1>
        <p>
            <span class="text-neutral-400 text-s pb-4 font-bold">
                <?= \date('m/d/Y H:i:s T', strtotime($feed['last_update'])) ?>
                &nbsp;|&nbsp;
                <a href="/rss?feed_id=<?= $feed['id'] ?>">RSS</a>
                &nbsp;|&nbsp;
                <a href="/refresh?feed_id=<?= $feed['id'] ?>">Refresh</a>
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
</div>

