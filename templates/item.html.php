<div class="container py-10 content-center clear">

    <h1 class="py-5 text-2xl">
        <a href="/feed?id=<?= $item['feed_id'] ?>"><?= $feed['name'] ?></a>
    </h1>

    <h1 class="text-xl pb-5"><?= $item['name'] ?></h1>

    <p><img src="/image?<?= 'item_id='.$item['id'] ?: 'feed_id'.$feed['id'] ?>" class="mx-auto w-3/5 border-solid border-neutral-800 border"></p>

<p><audio autoplay controls src="/audio?item_id=<?= $item['id'] ?>" class="w-full m-4 h-13"></audio></p>

    <p><?= $item['description'] ?></p>

</div>

<style type="text/css">
   #item-desc a {color: rgb(253, 230, 138);}
   #item-desc p {padding-bottom: 1em;}
</style>
<script type="text/javascript">
    (function() {
        const audio = document.querySelector('audio');
        const itemId = <?= $item['id'] ?>;
        const interval = (<?= $this->main->getConf('podsumer', 'playback_interval') ?? 5 ?>) * 1000;
        const rewind = <?= $this->main->getConf('podsumer', 'playback_rewind') ?? 5 ?>;

        fetch('/get_playback?item_id=' + itemId)
            .then(r => r.json())
            .then(d => {
                if (d.position > 0) {
                    audio.currentTime = Math.max(0, d.position - rewind);
                }
            });

        setInterval(() => {
            if (!audio.paused) {
                const params = new URLSearchParams();
                params.append('item_id', itemId);
                params.append('position', Math.floor(audio.currentTime));
                fetch('/set_playback', {method: 'POST', body: params});
            }
        }, interval);
    })();
</script>
