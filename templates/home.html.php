<div class="container p-10">


    <form method="POST" action="/add" enctype="multipart/form-data">
        <label>
            <textarea class="text-black" name="url" placeholder="Feed URLs, 1 per line" rows="2" cols="28"></textarea>
        </label>
        <br>
        <label>
            OPML:
            <input type="file" name="opml" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
        </label>
        <br>
        <input type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">

    </form>

    <? if (empty($feeds)): ?>
    <div class="container py-10 clear">
        <h1 class="text-2xl">No Feeds</h1>
    </div>

    <? else: ?>
    <? foreach ($feeds as $feed): ?>
    <div class="container py-10 clear">
        <img src="/file?hash=<?= $feed['image'] ?>" class="float-left w-32 pr-5">
        <h1 class="text-2xl">
            <a href="/feed?id=<?= $feed['id'] ?>">
            <?= $feed['name'] ?>
            </a>
        </h1>
        <p class="text-neutral-400 text-xs">
            <?= $feed['last_update'] ?>
        </p>
        <p>
            <?= $feed['description'] ?>
        </p>

        <p><a href="/rss?feed_id=<?= $feed['id'] ?>">RSS</a></p>
    </div>
    <? endforeach ?>
    <? endif ?>

</div>
