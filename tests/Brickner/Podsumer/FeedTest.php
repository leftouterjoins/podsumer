<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\Feed;
use \DateTime;
use \SimpleXMLElement;

final class FeedTest extends TestCase
{
    private Feed $feed;

    public function setUp(): void
    {
        $this->feed = new Feed('https://feeds.npr.org/500005/podcast.xml');
    }

    public function testLoadFeed(): void
    {
        $this->assertEquals(true, $this->feed->feedLoaded());
    }

    public function testLoadFeedBadURL(): void
    {
        $this->expectException(Exception::class);
        $feed = new Feed('example.com');
    }

    public function testGetTitle(): void
    {
        $this->assertEquals('NPR News Now', $this->feed->getTitle());
    }

    public function testGetLastUpdated(): void
    {
        $this->assertEquals(DateTime::class, $this->feed->getLastUpdated()::class);
    }

    public function testGetDescription(): void
    {
        $this->assertEquals(true, is_string($this->feed->getDescription()));
    }

    public function testGetImage(): void
    {
        $this->assertEquals(true, is_string($this->feed->getImage()));
    }

    public function testGetUrl(): void
    {
        $this->assertEquals(true, is_string($this->feed->getUrl()));
    }

    public function testGetUrlHash(): void
    {
        $this->assertEquals(true, is_string($this->feed->getUrlHash()));
    }

    public function testGetFeedItems(): void
    {
        $this->assertEquals(SimpleXMLElement::class, $this->feed->getFeedItems()::class);
    }

    public function testSetFeedId(): void
    {
        $this->feed->setFeedId(33);
        $this->assertEquals(33, $this->feed->getFeedId());
    }

    public function testGetFeedId(): void
    {
        $this->assertEquals(true, is_int($this->feed->getFeedId()));
    }

}

