<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\Main;

final class MainTest extends TestCase
{
    public string $root = __DIR__ . DIRECTORY_SEPARATOR . '../../..' . DIRECTORY_SEPARATOR;

    public function testConstruct(): void
    {
        $main = new Main($this->root, [], [], []);
        $this->assertEquals($main::class, Main::class);
    }

    public function testRun(): void
    {
        $env = [
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $request = [];
        $files = [];
        $main = new Main($this->root, $env, $request, $files);

        # Define a mock endpoint.

        #[Route('/', 'GET')]
        function dummyTestRun(array $args) {
            # asuume things went well.
            http_response_code(200);
        }

        $main->run();

        $this->assertEquals(http_response_code(), 200);
    }

    public function testRunNotFound(): void
    {
        $env = [
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/404',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $request = [];
        $files = [];
        $main = new Main($this->root, $env, $request, $files);

        $main->run();

        $this->assertEquals(http_response_code(), 404);
    }

    public function testRunException(): void
    {
        $env = [
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $request = [];
        $files = [];
        $main = new Main($this->root, $env, $request, $files);

        #[Route('/', 'GET')]
        function dummyTestException(array $args) {
            # asuume things went well.
            throw new Exception('Test exception');
        }

        $main->run();

        $this->assertEquals(http_response_code(), 500);
    }
}

