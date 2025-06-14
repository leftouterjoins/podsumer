<? echo '<?xml version="1.0" encoding="UTF-8"?>' ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:podcast="https://podcastindex.org/namespace/1.0">
  <channel>
    <title><?= $feed['name'] ?></title>
    <description><?=  $feed['description'] ?></description>
    <itunes:image href="<?= $host ?>/image?feed_id=<?= $feed['id'] ?>" />
    <itunes:summary><![CDATA[ <?= substr(strip_tags($feed['description']), 0, 4000) ?> ]]></itunes:summary>
    <itunes:explicit>false</itunes:explicit>
    <language>en-us</language>
    <generator>podsumer</generator>
    <lastBuildDate><?= $feed['last_update'] ?></lastBuildDate>
    <pubDate><?= $feed['last_update'] ?></pubDate>
    <atom:link href="<?= $host ?>/rss?feed_id=<?= $feed['id'] ?>" rel="self" type="application/rss+xml" />
    <link><?= $feed['url'] ?></link>
    <? foreach($items as $item): ?>
    <item>
      <title><?= $item['name'] ?></title>
      <itunes:title><?= $item['name'] ?></itunes:title>
      <description><![CDATA[ <?= $item['description'] ?> ]]></description>
      <itunes:summary><![CDATA[ <?= substr(strip_tags($item['description']), 0, 4000) ?> ]]></itunes:summary>
      <itunes:explicit>false</itunes:explicit>
      <pubDate><?= $item['published'] ?></pubDate>
      <enclosure url="<?= $host ?>/audio?item_id=<?= $item['id'] ?>" type="audio/mpeg" length="<?= $item['size'] ?>"/>
      <link><?= $host ?>/item?item_id=<?= $item['id'] ?></link>
      <guid isPermaLink="false"><?= $item['guid'] ?? ($host . '/item?item_id=' . $item['id']) ?></guid>
      <itunes:image href="<?= $host ?>/image?item_id=<?= $item['id'] ?>" />
    </item>
    <? endforeach ?>
  </channel>
</rss>

