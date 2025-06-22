<div class="container py-10">
    <? if (empty($segments)): ?>
    <div class="py-10">
        <h1 class="text-2xl">No Segments</h1>
    </div>
    <? else: ?>
    <h1 class="text-2xl pb-4"><?= htmlspecialchars($item['name'] ?? '') ?></h1>
    <? foreach ($segments as $seg): ?>
    <div class="py-4">
        <audio controls src="/file?file_id=<?= $seg['file_id'] ?>"></audio>
        <? if ($seg['has_sponsor']): ?>
        <span class="text-amber-500">Sponsor</span>
        <? else: ?>
        <span class="text-green-600">No Sponsor</span>
        <? endif ?>
        &nbsp;
        <a href="/clips?segment_id=<?= $seg['id'] ?>" class="underline">Clips</a>
        &nbsp;
        <a href="/delete_segment?segment_id=<?= $seg['id'] ?>" class="text-red-600 underline">Delete</a>
    </div>
    <? endforeach ?>
    <? endif ?>
</div>
