<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\PodcastIndex;

final class PodcastIndexTest extends TestCase
{
    public function testSearchNoCredentials(): void
    {
        $results = PodcastIndex::search('test', 1, '', '');
        $this->assertEquals([], $results);
    }
}
