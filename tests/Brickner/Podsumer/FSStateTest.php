<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\Main;
use Brickner\Podsumer\FSState;
use Brickner\Podsumer\Feed;
use Brickner\Podsumer\File;

final class FSStateTest extends TestCase
{
    const TEST_FEED_URL = 'https://feeds.npr.org/500005/podcast.xml';
    private Main $main;
    private FSState $state;
    private Feed $feed;

    public string $root = __DIR__ . DIRECTORY_SEPARATOR . '../../..' . DIRECTORY_SEPARATOR;

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

        $this->assertEquals(
            $this->root . 'state/media_test',
            $this->state->getMediaDir()
        );
    }

    public function testGetFeedDir()
    {
        $feed = new Feed(self::TEST_FEED_URL);
        $name = $feed->getTitle();
        $this->main->getState()->addFeed($feed);
        $feed = $this->main->getState()->getFeed(1);

        $this->assertEquals(
            $this->root . 'state/media_test/' . $name,
            $this->main->getState()->getFeedDir($feed['name'])
        );
    }

    public function testBadMediaDir()
    {
        $this->expectException(Exception::class);

        $this->main->setInstallPath('/');
        $this->main->setConf('/dev/random', 'podsumer', 'media_dir');

        $this->feed = new Feed(self::TEST_FEED_URL);
        $this->main->getState()->addFeed($this->feed);
    }

    public function testDeleteFeed()
    {
        $this->expectNotToPerformAssertions();

        $this->feed = new Feed(self::TEST_FEED_URL);
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

        $this->feed = new Feed(self::TEST_FEED_URL);
        $this->state->addFeed($this->feed);

        $item = $this->main->getState()->getFeedItems(1)[0];

        $feed_data = $this->main->getState()->getFeed(1);

        $file = new File($this->main);
        $file_id = $file->cacheUrl($item['audio_url'], $feed_data);
        $this->main->getState()->setItemAudioFile($item['id'], $file_id);

        $this->state->deleteItemMedia($item['id']);
     }

}

