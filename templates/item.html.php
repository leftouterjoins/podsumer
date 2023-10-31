<div class="container p-10 content-center">
    <p class="py-5">
        <a href="/feed?id=<?= $item['feed_id'] ?>">Back to Feed</a>
    </p>

    <h1 class="text-3xl pb-5"><?= $item['name'] ?></h1>
    <img src="/file?hash=<?= $item['image'] ?: $feed_image ?>" class="mx-auto w-2/5 border-solid border-neutral-800 border">
    <audio controls src="/file?url=<?= urlencode($item['audio_url']) ?>" class="w-full p-4 h-13"></audio>
    <div class="w-full p-10">
    <?= $item['description'] ?>
</div>
