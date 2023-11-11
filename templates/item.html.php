<div class="container py-10 content-center">

    <h1 class="py-5 text-2xl">
    <a href="/feed?id=<?= $item['feed_id'] ?>"><?= $feed['name'] ?></a>
    </h1>

    <h1 class="text-xl pb-5"><?= $item['name'] ?></h1>
    <img src="/image?<?= 'item_id='.$item['id'] ?: 'feed_id'.$feed['id'] ?>" class="mx-auto w-3/5 border-solid border-neutral-800 border">
    <audio autoplay controls src="/audio?item_id=<?= $item['id'] ?>" class="w-full p-4 h-13"></audio>
    <div class="w-full p-10">
        <?= htmlspecialchars_decode(nl2br($item['description'])) ?>
    </div>

</div>

