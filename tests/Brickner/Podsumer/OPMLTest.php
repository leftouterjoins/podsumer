<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\OPML;

final class OPMLTest extends TestCase
{
    public function testParse()
    {
        $path = realpath(__DIR__ . '/../../fixtures/states.opml');
        $opml = OPML::parse(['tmp_name' => $path]);
        $this->assertEquals(true, is_array($opml));
    }
}

