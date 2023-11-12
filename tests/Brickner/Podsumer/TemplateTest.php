<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\Template;
use Brickner\Podsumer\Main;

final class TemplateTest extends TestCase
{

    public string $root = __DIR__ . DIRECTORY_SEPARATOR . '../../..' . DIRECTORY_SEPARATOR;

    protected Main $main;

    public function testTemplate(): void
    {
        ob_start();

        $env = [
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $this->main = new Main($this->root, $env, [], [], true);
        Template::render($this->main, 'test', ['test' => 'test']);
        $out = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('test', $out);
    }

    public function testXmlTemplate(): void
    {
        ob_start();

        $env = [
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $this->main = new Main($this->root, $env, [], [], true);
        Template::renderXml($this->main, 'feed', ['test' => 'test']);
        $out = ob_get_contents();
        ob_end_clean();

        $this->assertEquals('test', $out);
    }
}

