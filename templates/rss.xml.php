<? echo '<?xml version="1.0" encoding="UTF-8"?>' ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:podcast="https://podcastindex.org/namespace/1.0">
  <channel>
    <title><?= $feed['name'] ?></title>
    <description><?=  $feed['description'] ?></description>
    <itunes:image href="<?= $host ?>/image?feed_id=<?= $feed['id'] ?>" />
    <language>en-us</language>
    <generator>podsumer</generator>
    <lastBuildDate><?= date('r', strtotime($feed['last_update'])) ?></lastBuildDate>
    <pubDate><?= date('r', strtotime($feed['last_update'])) ?></pubDate>
    <atom:link href="<?= $host ?>/rss?feed_id=<?= $feed['id'] ?>" rel="self" type="application/rss+xml" />
    <link><?= $feed['url'] ?></link>
    <itunes:category text="Technology">
      <itunes:category text="Podcasting" />
    </itunes:category>
    <itunes:explicit>no</itunes:explicit>
    <itunes:owner>
      <itunes:name><?= $feed['owner_name'] ?? 'Podcast Owner' ?></itunes:name>
      <itunes:email><?= $feed['owner_email'] ?? 'podcast@example.com' ?></itunes:email>
    </itunes:owner>
    <itunes:author><?= $feed['author'] ?? $feed['name'] ?></itunes:author>
    <itunes:type>episodic</itunes:type>
    <? foreach($items as $item): ?>
    <item>
      <title><?= $item['name'] ?></title>
      <description><![CDATA[ <?= $item['description'] ?> ]]></description>
      <pubDate><?= date('r', strtotime($item['published'])) ?></pubDate>
      <enclosure url="<?= $host ?>/audio?item_id=<?= $item['id'] ?>" type="audio/mpeg" length="<?= $item['size'] ?>"/>
      <link><?= $host ?>/item?item_id=<?= $item['id'] ?></link>
      <guid isPermaLink="false"><?= $host ?>/item?item_id=<?= $item['id'] ?></guid>
      <itunes:image href="<?= $host ?>/image?item_id=<?= $item['id'] ?>" />
      <itunes:explicit>no</itunes:explicit>
    </item>
    <? endforeach ?>
  </channel>
</rss>

