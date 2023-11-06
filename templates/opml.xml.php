<?= '<?xml version="1.0" encoding="UTF-8"?>' ?>
<opml version="2.0">
    <head>
        <title>Podsumer Feeds</title>
        <dateCreated><?= date('r') ?></dateCreated>
    </head>
    <body>
        <? foreach ($feeds as $feed): ?>
        <outline title="<?= htmlspecialchars($feed['name']) ?>" xmlUrl="http://casts.brickner.cloud/rss?feed_id=<?= $feed['id'];?>" text="<?= htmlspecialchars($feed['description']); ?>" type="rss"></outline>
        <? endforeach ?>
    </body>
</opml>
