<div class="container p-10">


    <form method="POST" action="/add" enctype="multipart/form-data">
        <label>
            <textarea class="text-black" name="url" placeholder="Feed URLs, 1 per line" rows="2" cols="28"></textarea>
        </label>
        <br><br>
        <label>
            OPML:
            <input type="file" name="opml" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        </label>
        <br>
        <br>
        <input type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">

    </form>

    <? if (empty($feeds)): ?>
    <div class="container py-10 clear">
        <h1 class="text-2xl">No Feeds</h1>
    </div>

    <? else: ?>
    <p class="pt-8 font-bold">
        <a href="/opml">Export OPML</a>
    </p>
    <? foreach ($feeds as $feed): ?>
    <div class="container py-10 clear">
        <a href="/feed?id=<?= $feed['id'] ?>">
            <img src="/file?hash=<?= $feed['image'] ?>" class="float-left w-32 pr-5">
        </a>
        <h1 class="text-2xl">
            <a href="/feed?id=<?= $feed['id'] ?>">
            <?= $feed['name'] ?>
            </a>
        </h1>
        <p class="text-neutral-400 text-xs pt-1 font-bold">
            <?= $feed['last_update'] ?>
            &nbsp;|&nbsp;
            <a href="/rss?feed_id=<?= $feed['id'] ?>">RSS</a>
            &nbsp;|&nbsp;
            <a href="/refresh?feed_id=<?= $feed['id'] ?>">Refresh</a>
        </p>
        <p>
            <?= $feed['description'] ?>
        </p>

    </div>
    <? endforeach ?>
    <? endif ?>

</div>
