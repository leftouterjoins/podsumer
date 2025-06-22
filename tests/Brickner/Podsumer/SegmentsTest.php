<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\Main;
use Brickner\Podsumer\State;
use Brickner\Podsumer\Feed;

final class SegmentsTest extends TestCase
{
    const TEST_FEED_URL = 'https://feeds.npr.org/500005/podcast.xml';
    public string $root = __DIR__ . DIRECTORY_SEPARATOR . '../../..' . DIRECTORY_SEPARATOR;

    private Main $main;
    private State $state;

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
        @unlink($tmp_main->getStateFilePath());

        $this->main = new Main($this->root, $env, [], [], true);
        $this->state = new State($this->main);

        $feed = new Feed(self::TEST_FEED_URL);
        $this->state->addFeed($feed);
    }

    public function testAddSegmentAndClip(): void
    {
        $item = $this->state->getFeedItem(1);
        $feed = $this->state->getFeed($item['feed_id']);

        $file_id = $this->state->addFile('dummy.mp3', 'abc', $feed);
        $segment_id = $this->state->addSegment($item['id'], $file_id, 0.0, 1.0, false);
        $segments = $this->state->getSegmentsForItem($item['id']);
        $this->assertGreaterThan(0, count($segments));

        $clip_file_id = $this->state->addFile('clip.mp3', 'xyz', $feed);
        $clip_id = $this->state->addClip($segment_id, $clip_file_id, false, null);
        $clips = $this->state->getClipsForSegment($segment_id);
        $this->assertGreaterThan(0, count($clips));
    }
}
