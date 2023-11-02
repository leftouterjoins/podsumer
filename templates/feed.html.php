<div class="container py-10">
    <h1 class="text-3xl py-4"><?= $feed['name'] ?></h1>

    <p class="py-4"><?= $feed['description'] ?></p>

    <p class="font-bold py-4">
        <a href="/rss?feed_id=<?= $feed['id'] ?>">RSS</a>
        &nbsp;|&nbsp;
        <a href="/refresh?feed_id=<?= $feed['id'] ?>">Refresh</a>
    </p>

    <? foreach ($items as $id => $item): ?>
    <div class="w-full clear-left text-xl py-8">
        <a href="/item?item_id=<?= $item['id'] ?>">
            <img src="/file?file_id=<?= $item['image'] ?: $feed['image'] ?>" class="w-32 border-solid border-neutral-800 border inline float-left mr-4">
        </a>
        <a href="/item?item_id=<?= $item['id'] ?>" class="text-2xl">
            <?= $item['name'] ?>
        </a>
        <br>
        <span class="text-neutral-500">
        <?= round($item['size'] / 1024 / 1024, 1) ?>MB
        &nbsp;|&nbsp;
        <?= date('m/d/Y', strtotime($item['published'])); ?>
        <? if (!empty($item['audio_file'])) { ?>
        &nbsp;|&nbsp;
        <a href="/delete_audio?item_id=<?= $item['id'] ?>">Delete Audio</a>
        <? } ?>
        </span>
        <br>
        <?= substr($item['description'], 0, 360); ?>
    </div>
    <? endforeach ?>
</div>
