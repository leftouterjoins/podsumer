<div class="container p-10">
<p>
    <a href="/">Back to Feeds</a>
</p>
<table class="w-full">
<? foreach ($items as $id => $item): ?>
<tr>
    <td class="py-2 w-20">
    <img src="<?= $item['image'] ?: $feed['image'] ?>" class="w-16 border-solid border-neutral-800 border">
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
