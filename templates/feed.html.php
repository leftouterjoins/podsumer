<div class="container p-10">
<p>
    <a href="/">Back to Feeds</a>
</p>
<h1 class="text-3xl py-4"><?= $feed['name'] ?></h1>
<p class="py-4"><?= $feed['description'] ?></p>
<p class="font-bold py-4">
    <a href="/rss?feed_id=<?= $feed['id'] ?>">RSS</a>
    &nbsp;|&nbsp;
    <a href="/refresh?feed_id=<?= $feed['id'] ?>">Refresh</a>
</p>
<table class="w-full">
<? foreach ($items as $id => $item): ?>
<tr>
    <td class="py-2 w-20">
        <a href="/item?item_id=<?= $item['id'] ?>">
            <img src="/file?hash=<?= $item['image'] ?: $feed['image'] ?>" class="w-16 border-solid border-neutral-800 border">
        </a>
    </td>
    <td>
        <a href="/item?item_id=<?= $item['id'] ?>">
            <?= $item['name'] ?>
        </a>
    </td>
    <td><?= round($item['size'] / 1024 / 1024, 1) ?>MB</td>
    <td><?= date('m/d/Y', strtotime($item['published'])); ?></td>
</tr>
<? endforeach ?>
</table>
</div>
