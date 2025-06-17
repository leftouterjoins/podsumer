<div class="container py-10">
    <? if (empty($items)): ?>
    <div class="container py-10 clear">
        <h1 class="text-2xl">No Episodes</h1>
    </div>
    <? else: ?>
    <? foreach ($items as $item): ?>
    <div class="w-full clear-left py-8">
        <a href="/item?item_id=<?= $item['id'] ?>">
            <img src="/image?<?= !empty($item['item_image']) ? 'item_id=' . $item['id'] : 'feed_id=' . $item['feed_id'] ?>" class="w-32 border-solid border-neutral-800 border inline float-left mr-4">
        </a>
        <a href="/item?item_id=<?= $item['id'] ?>" class="text-xl">
            <?= $item['name'] ?>
        </a>
        <br>
        <span class="text-neutral-500">
            <?= $item['feed_name'] ?>
            &nbsp;|&nbsp;
            <?= round($item['size'] / 1024 / 1024, 1) ?>MB
            &nbsp;|&nbsp;
            <?= date('m/d/Y', strtotime($item['published'])); ?>
            <? if (!empty($item['audio_file'])) { ?>
            &nbsp;|&nbsp;
            <a href="/delete_audio?item_id=<?= $item['id'] ?>">Delete Audio</a>
            <? } ?>
        </span>
        <br>
        <?= substr(strip_tags($item['description']), 0, 360); ?>
    </div>
    <? endforeach ?>
    <div class="py-4 font-bold">
        <? if ($page > 1) { ?>
            <a href="/episodes?page=<?= $page - 1 ?>">Previous</a>
        <? } ?>
        <? if ($page < $page_count) { ?>
            <? if ($page > 1) { ?>&nbsp;|&nbsp;<? } ?>
            <a href="/episodes?page=<?= $page + 1 ?>">Next</a>
        <? } ?>
        <span>&nbsp;&nbsp;Page <?= $page ?> of <?= $page_count ?></span>
    </div>
    <? endif ?>
</div>
