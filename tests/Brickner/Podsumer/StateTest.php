<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\Main;
use Brickner\Podsumer\State;
use Brickner\Podsumer\Feed;

final class StateTest extends TestCase
{
    private static string $feedFile;
    private Main $main;
    private State $state;
    private Feed $feed;

    public string $root = __DIR__ . DIRECTORY_SEPARATOR . '../../..' . DIRECTORY_SEPARATOR;

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

    protected function setUp(): void
    {
        $env = [
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $tmp_main = new Main($this->root, $env, [], [], true);
        unlink($tmp_main->getStateFilePath());

        $this->main = new Main($this->root, $env, [], [], true);
        $this->state = new State($this->main);
    }

    public function testGetStateDirPath()
    {
        $path = dirname($this->main->getStateFilePath());
        $this->assertEquals($path, $this->state->getStateDirPath());
    }

    public function testAddFeed()
    {
        $this->expectNotToPerformAssertions();
        $this->feed = new Feed('file://' . self::$feedFile);
        $this->state->addFeed($this->feed);
    }

    public function testAddDuplicateFeed()
    {
        $this->expectNotToPerformAssertions();
        $this->feed = new Feed('file://' . self::$feedFile);
        $this->state->addFeed($this->feed);
        $this->state->addFeed($this->feed);
    }

    public function testGetFeed()
    {
        $this->feed = new Feed('file://' . self::$feedFile);
        $this->state->addFeed($this->feed);
        $feed = $this->state->getFeed(1);
        $this->assertEquals(1, $feed['id']);
    }

    public function testGetFeeds()
    {
        $this->feed = new Feed('file://' . self::$feedFile);
        $this->state->addFeed($this->feed);
        $feeds = $this->state->getFeeds();
        $this->assertEquals(1, count($feeds));
    }

    public function testGetFeedItem()
    {
        $this->feed = new Feed('file://' . self::$feedFile);
        $this->state->addFeed($this->feed);
        $item = $this->state->getFeedItem(1);
        $this->assertEquals(1, $item['id']);
    }

    public function testGetFeedItems()
    {
        $this->feed = new Feed('file://' . self::$feedFile);
        $this->state->addFeed($this->feed);
        $items = $this->state->getFeedItems(1);
        $this->assertEquals(1, count($items));
    }

    public function testGetFeedByHash()
    {
        $this->feed = new Feed('file://' . self::$feedFile);
        $this->state->addFeed($this->feed);
        $feed = $this->state->getFeed(1);
        $hash = $feed['url_hash'];
        $feed_by_hash = $this->state->getFeedByHash($hash)[0];

        $this->assertEquals(1, $feed_by_hash['id']);
    }

    public function testDeleteFeed()
    {
        $this->expectNotToPerformAssertions();
        $this->feed = new Feed('file://' . self::$feedFile);
        $this->state->addFeed($this->feed);
        $this->state->deleteFeed(1);
    }

    public function testDeleteItemMedia()
    {
        $this->expectNotToPerformAssertions();
        $this->feed = new Feed('file://' . self::$feedFile);
        $this->state->addFeed($this->feed);
        $item = $this->state->getFeedItem(1);
        $this->state->deleteItemMedia($item['id']);
     }

    public function testGetVersion()
    {
        // Assert it is greater than 0
        $this->assertGreaterThan(0, $this->state->getVersion());
    }
}

