<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\Feed;

final class FeedTest extends TestCase
{
    private Feed $feed;
    private static string $feedFile;

    public static function setUpBeforeClass(): void
    {
        $path = realpath(__DIR__ . '/../../fixtures/feed.xml');
        $audio = realpath(__DIR__ . '/../../fixtures/audio.mp3');
        $image = realpath(__DIR__ . '/../../fixtures/image.jpg');
        $contents = file_get_contents($path);
        $contents = str_replace('AUDIO_FILE_URL', 'file://' . $audio, $contents);
        $contents = str_replace('IMAGE_FILE_URL', 'file://' . $image, $contents);
        self::$feedFile = tempnam(sys_get_temp_dir(), 'feed');
        file_put_contents(self::$feedFile, $contents);
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$feedFile);
    }

    public function setUp(): void
    {
        $this->feed = new Feed('file://' . self::$feedFile);
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
        $this->assertEquals('Test Feed', $this->feed->getTitle());
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
}

