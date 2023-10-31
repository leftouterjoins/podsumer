<? echo '<?xml version="1.0" encoding="UTF-8"?>' ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd">
  <channel>
    <title><?= htmlspecialchars($feed['name']) ?></title>
    <description><?= htmlspecialchars($feed['description']) ?></description>
    <itunes:image href="http://brickner.cloud:8433/file?hash=<?= $feed['image'] ?>" />
    <language>en-us</language>
    <generator>podsumer</generator>
    <lastBuildDate><?= $feed['last_update'] ?></lastBuildDate>
    <pubDate><?= $feed['last_update'] ?></pubDate>
    <atom:link href="http://brickner.cloud:8433/rss?feed_id=<?= $feed['id'] ?>" rel="self" type="application/rss+xml" />
    <link><?= $feed['url'] ?></link>
    <? foreach($items as $item): ?>
    <item>
      <title><?= htmlspecialchars($item['name']) ?></title>
      <description><?= htmlspecialchars($item['description']) ?></description>
      <pubDate><?= $item['published'] ?></pubDate>
      <enclosure url="http://brickner.cloud:8433/file?url=<?= urlencode($item['audio_url']) ?>" type="audio/mp3" length="<?= $item['size'] ?>"/>
      <link>http://brickner.cloud:8433/item?item_id=<?= $item['id'] ?></link>
      <guid>http://brickner.cloud:8433/item?item_id=<?= $item['id'] ?></guid>
      <itunes:image href="http://brickner.cloud:8433/file?hash=<?= $item['image'] ?>" />
    </item>
    <? endforeach ?>
  </channel>
</rss>

