<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\Template;
use Brickner\Podsumer\Main;

final class TemplateTest extends TestCase
{

    public string $root = __DIR__ . DIRECTORY_SEPARATOR . '../../..' . DIRECTORY_SEPARATOR;

    public function testTemplate(): void
    {
        ob_start();
        $this->main = new Main($this->root, [], [], [], true);
        Template::render($this->main, 'test', ['test' => 'test']);
        $out = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('test', $out);
    }

    public function testXmlTemplate(): void
    {
        ob_start();
        $this->main = new Main($this->root, [], [], [], true);
        Template::renderXml($this->main, 'feed', ['test' => 'test']);
        $out = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('test', $out);
    }
}

