<div class="container p-10 content-center">
    <p class="py-5">
        <a href="/feed?id=<?= $feed_id ?>">Back to Feed</a>
    </p>

    <h1 class="text-3xl pb-5"><?= $item['title'] ?></h1>
    <img src="<?= $item['art_url'] ?? $feed_art_url ?>" class="mx-auto w-2/5 border-solid border-neutral-800 border">
    <audio controls src="<?= $item['audio_url'] ?>" class="w-full p-4 h-13"></audio>
    <div class="w-full p-10">
    <?= $item['description'] ?>
</div>
