<? echo '<?xml version="1.0" encoding="UTF-8"?>' ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:podcast="https://podcastindex.org/namespace/1.0">
  <channel>
    <title><?= htmlspecialchars($feed['name'], ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></title>
    <description><?= htmlspecialchars($feed['description'], ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></description>
    <itunes:image href="<?= $host ?>/image?feed_id=<?= $feed['id'] ?>" />
    <itunes:summary><![CDATA[ <?= substr(html_entity_decode(strip_tags($feed['description'])), 0, 4000) ?> ]]></itunes:summary>
    <itunes:category text="General" />
    <itunes:email>no-reply@example.com</itunes:email>
    <itunes:explicit>false</itunes:explicit>
    <language>en-us</language>
    <generator>podsumer</generator>
    <lastBuildDate><?= $feed['last_update'] ?></lastBuildDate>
    <pubDate><?= $feed['last_update'] ?></pubDate>
    <atom:link href="<?= $host ?>/rss?feed_id=<?= $feed['id'] ?>" rel="self" type="application/rss+xml" />
    <link><?= htmlspecialchars($feed['url'], ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></link>
    <? foreach($items as $item): ?>
    <item>
      <title><?= htmlspecialchars($item['name'], ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></title>
      <itunes:title><?= htmlspecialchars($item['name'], ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></itunes:title>
      <description><![CDATA[ <?= $item['description'] ?> ]]></description>
      <itunes:summary><![CDATA[ <?= substr(html_entity_decode(strip_tags($item['description'])), 0, 4000) ?> ]]></itunes:summary>
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

