<div class="container py-10">
    <h1 class="text-2xl">Search Podcasts</h1>
    <form method="GET" action="/search" class="py-4">
        <input type="text" name="q" value="<?= $q ?>" class="text-black w-1/2" placeholder="Search term">
        <input type="submit" class="bg-neutral-500 text-white font-bold py-2 px-4 rounded" value="Search">
    </form>

    <? if ($q !== '' && empty($feeds)) { ?>
        <p>No Results</p>
    <? } ?>

    <? foreach ($feeds as $feed): ?>
    <div class="container py-10 clear-left">
        <? if (!empty($feed['artwork'])) { ?>
        <img src="<?= htmlspecialchars($feed['artwork'], ENT_QUOTES) ?>" class="float-left w-48 pr-5">
        <? } ?>
        <h1 class="text-2xl pb-2"><?= htmlspecialchars($feed['title'], ENT_QUOTES) ?></h1>
        <form method="POST" action="/add" class="text-neutral-400 text-s pb-2 font-bold">
            <input type="hidden" name="url" value="<?= htmlspecialchars($feed['url'], ENT_QUOTES) ?>">
            <button type="submit" class="underline">Subscribe</button>
        </form>
        <p><?= htmlspecialchars(substr($feed['description'] ?? '', 0, 360), ENT_QUOTES) ?></p>
    </div>
    <? endforeach ?>

    <? if (!empty($feeds)) { ?>
    <div class="py-4 font-bold">
        <? if ($page > 1) { ?>
            <a href="/search?q=<?= urlencode($q) ?>&page=<?= $page - 1 ?>">Previous</a>
        <? } ?>
        <? if ($page < $page_count) { ?>
            <? if ($page > 1) { ?>&nbsp;|&nbsp;<? } ?>
            <a href="/search?q=<?= urlencode($q) ?>&page=<?= $page + 1 ?>">Next</a>
        <? } ?>
        <span>&nbsp;&nbsp;Page <?= $page ?> of <?= $page_count ?></span>
    </div>
    <? } ?>
</div>

