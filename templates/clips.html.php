<div class="container py-10">
    <? if (empty($clips)): ?>
    <div class="py-10">
        <h1 class="text-2xl">No Clips</h1>
    </div>
    <? else: ?>
    <? foreach ($clips as $clip): ?>
    <div class="py-4">
        <audio controls src="/file?file_id=<?= $clip['file_id'] ?>"></audio>
        <? if ($clip['has_sponsor']): ?>
        <span class="text-amber-500">Sponsor</span>
        <? else: ?>
        <span class="text-green-600">No Sponsor</span>
        <? endif ?>
        <? if (!empty($clip['spectrogram_file'])): ?>
        <img src="/file?file_id=<?= $clip['spectrogram_file'] ?>" class="w-64">
        <? endif ?>
        &nbsp;
        <a href="/delete_clip?clip_id=<?= $clip['id'] ?>" class="text-red-600 underline">Delete</a>
    </div>
    <? endforeach ?>
    <? endif ?>
</div>
