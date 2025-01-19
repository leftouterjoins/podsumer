<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<opml version="2.0">
    <head>
        <title>Podsumer Feeds</title>
        <dateCreated><?= date('r') ?></dateCreated>
    </head>
    <body>
        <? foreach ($feeds as $feed): ?>
        <outline text="<?= htmlspecialchars($feed['name']) ?>" xmlUrl="<?= $host ?>/rss?feed_id=<?= $feed['id'];?>"  htmlUrl="<?= $host ?>/feed?id=<?= $feed['id'];?>" type="rss"></outline>
        <? endforeach ?>
    </body>
</opml>
