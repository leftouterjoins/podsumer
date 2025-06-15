<? echo '<?xml version="1.0" encoding="UTF-8"?>' ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:podcast="https://podcastindex.org/namespace/1.0">
  <channel>
    <title><?= htmlspecialchars($feed['name'], ENT_QUOTES | ENT_XML1, 'UTF-8') ?></title>
    <description><?= htmlspecialchars($feed['description'], ENT_QUOTES | ENT_XML1, 'UTF-8') ?></description>
    <itunes:image href="<?= htmlspecialchars($host . '/image?feed_id=' . $feed['id'], ENT_QUOTES | ENT_XML1, 'UTF-8') ?>" />
    <language>en-us</language>
    <generator>podsumer</generator>
    <lastBuildDate><?= date('r', strtotime($feed['last_update'])) ?></lastBuildDate>
    <pubDate><?= date('r', strtotime($feed['last_update'])) ?></pubDate>
    <atom:link href="<?= htmlspecialchars($host . '/rss?feed_id=' . $feed['id'], ENT_QUOTES | ENT_XML1, 'UTF-8') ?>" rel="self" type="application/rss+xml" />
    <link><?= htmlspecialchars($feed['url'], ENT_QUOTES | ENT_XML1, 'UTF-8') ?></link>
    <itunes:category text="Technology">
      <itunes:category text="Podcasting" />
    </itunes:category>
    <itunes:explicit>no</itunes:explicit>
    <itunes:owner>
      <itunes:name><?= htmlspecialchars($feed['owner_name'] ?? 'Podcast Owner', ENT_QUOTES | ENT_XML1, 'UTF-8') ?></itunes:name>
      <itunes:email><?= htmlspecialchars($feed['owner_email'] ?? 'podcast@example.com', ENT_QUOTES | ENT_XML1, 'UTF-8') ?></itunes:email>
    </itunes:owner>
    <itunes:author><?= htmlspecialchars($feed['author'] ?? $feed['name'], ENT_QUOTES | ENT_XML1, 'UTF-8') ?></itunes:author>
    <itunes:type>episodic</itunes:type>
    <? foreach($items as $item): ?>
    <item>
      <title><?= htmlspecialchars($item['name'], ENT_QUOTES | ENT_XML1, 'UTF-8') ?></title>
      <description><![CDATA[ <?= $item['description'] ?> ]]></description>
      <pubDate><?= date('r', strtotime($item['published'])) ?></pubDate>
      <enclosure url="<?= htmlspecialchars($host . '/audio?item_id=' . $item['id'], ENT_QUOTES | ENT_XML1, 'UTF-8') ?>" type="audio/mpeg" length="<?= intval($item['size']) ?>"/>
      <link><?= htmlspecialchars($host . '/item?item_id=' . $item['id'], ENT_QUOTES | ENT_XML1, 'UTF-8') ?></link>
      <guid isPermaLink="false"><?= htmlspecialchars($host . '/item?item_id=' . $item['id'], ENT_QUOTES | ENT_XML1, 'UTF-8') ?></guid>
      <itunes:image href="<?= htmlspecialchars($host . '/image?item_id=' . $item['id'], ENT_QUOTES | ENT_XML1, 'UTF-8') ?>" />
      <itunes:explicit>no</itunes:explicit>
    </item>
    <? endforeach ?>
  </channel>
</rss>

