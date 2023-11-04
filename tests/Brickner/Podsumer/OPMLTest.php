<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\OPML;

final class OPMLTest extends TestCase
{
    public function testParse()
    {
        $opml_data = file_get_contents('http://hosting.opml.org/dave/spec/states.opml');
        $tmp = tempnam("/tmp", "opml-test");
        file_put_contents($tmp, $opml_data);
        $opml = OPML::parse(['tmp_name' => $tmp]);
        $this->assertEquals(true, is_array($opml));
        unlink($tmp);
    }
}

