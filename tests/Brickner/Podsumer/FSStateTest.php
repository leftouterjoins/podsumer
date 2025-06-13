<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\Main;
use Brickner\Podsumer\FSState;
use Brickner\Podsumer\Feed;
use Brickner\Podsumer\File;

final class FSStateTest extends TestCase
{
    private static string $feedFile;
    private Main $main;
    private FSState $state;
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
        exec('rm -rf ' . $this->root . '/state/media_test');

        $this->main = new Main($this->root, $env, [], [], true);

        $this->main->setConf(true, 'podsumer', 'store_media_on_disk');
        $this->main->setConf('state/media_test', 'podsumer', 'media_dir');

        $this->state = new FSState($this->main);
        $this->main->setState($this->state);
    }

    public function testGetMediaDir()
    {
        # Write this test

        $this->assertTrue(
            str_ends_with($this->state->getMediaDir(), 'state/media_test')
        );
    }

    public function testGetFeedDir()
    {
        $feed = new Feed('file://' . self::$feedFile);
        $name = $feed->getTitle();
        $this->main->getState()->addFeed($feed);
        $feed = $this->main->getState()->getFeed(1);

        $this->assertTrue(
            str_ends_with(
                $this->main->getState()->getFeedDir($feed['name']),
                "state/media_test/$name"
            )
        );
    }

    public function testBadMediaDir()
    {
        $this->expectException(Exception::class);

        $this->main->setInstallPath('/');
        $this->main->setConf('/dev/random', 'podsumer', 'media_dir');

        $this->feed = new Feed('file://' . self::$feedFile);
        $this->main->getState()->addFeed($this->feed);
    }

    public function testDeleteFeed()
    {
        $this->expectNotToPerformAssertions();

        $this->feed = new Feed('file://' . self::$feedFile);
        $feed_id = $this->main->getState()->addFeed($this->feed);
        $feed_data = $this->main->getState()->getFeed($feed_id);

        $item = $this->main->getState()->getFeedItems(1)[0];
        $file = new File($this->main);
        $file_id = $file->cacheUrl($item['audio_url'], $feed_data);
        $this->main->getState()->setItemAudioFile($item['id'], $file_id);

        $this->main->getState()->deleteFeed(1);
    }

    public function testDeleteItemMedia()
    {
        $this->expectNotToPerformAssertions();

        $this->feed = new Feed('file://' . self::$feedFile);
        $this->state->addFeed($this->feed);

        $item = $this->main->getState()->getFeedItems(1)[0];

        $feed_data = $this->main->getState()->getFeed(1);

        $file = new File($this->main);
        $file_id = $file->cacheUrl($item['audio_url'], $feed_data);
        $this->main->getState()->setItemAudioFile($item['id'], $file_id);

        $this->state->deleteItemMedia($item['id']);
     }

}

