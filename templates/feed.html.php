<div class="container p-10">
<p>
    <a href="/">Back to Feeds</a>
</p>
<table class="w-full">
<? foreach ($feed['items'] as $id => $item): ?>
<tr>
    <td class="py-2 w-20">
        <img src="<?= $item['art_url'] ?: $feed['channel_art'] ?>" class="w-16 border-solid border-neutral-800 border">
    </td>
    <td>
        <a href="/item?feed_id=<?= $feed['id'] ?>&item_id=<?= $id ?>">
            <?= $item['title'] ?>
        </a>
    </td>
    <td><?= round($item['length'] / 1024 / 1024, 1) ?>MB</td>
    <td><?= date('m/d/Y', strtotime($item['pubDate'])); ?></td>
</tr>
<? endforeach ?>
</table>
</div>
