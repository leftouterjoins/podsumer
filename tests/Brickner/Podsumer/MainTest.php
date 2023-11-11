<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\Main;
use Brickner\Podsumer\State;

final class MainTest extends TestCase
{
    public string $root = __DIR__ . DIRECTORY_SEPARATOR . '../../..' . DIRECTORY_SEPARATOR;

    public static function setupBeforeClass(): void
    {
        #[Route('/', 'GET')]
        function dummyTestEndpoint200(array $args) {
            # asuume things went well.
            http_response_code(200);
        }

        #[Route('/exception', 'GET')]
        function dummyTestEndpointWithExecption(array $args) {
            # asuume things went well.
            throw new Exception('Test exception');
        }
    }

    public function testConstruct(): void
    {
        $main = new Main($this->root, [], [], []);
        $this->assertEquals($main::class, Main::class);
    }

    public function testRun(): void
    {
        $env = ['REQUEST_URI' => '/'];

        $main = $this->dummyMain($env);
        $main->run();

        $this->assertEquals(http_response_code(), 200);
    }

    public function testRunNotFound(): void
    {
        $env = ['REQUEST_URI' => '/made-up-url'];

        $main = $this->dummyMain($env);
        $main->run();

        $this->assertEquals(http_response_code(), 404);
    }

    public function testRunException(): void
    {
        $env = ['REQUEST_URI' => '/exception'];

        $main = $this->dummyMain($env);
        $main->run();

        $this->assertEquals(http_response_code(), 500);
    }

    public function testGetUrl(): void
    {
        $env = ['REQUEST_URI' => '/home'];

        $main = $this->dummyMain($env);
        $url = $main->getUrl();

        $this->assertEquals('http://example.com/home', $url);
    }

    public function testGetArg(): void
    {
        $request = ['arg1' => 'hello'];

        $main = $this->dummyMain([], $request);
        $arg = $main->getArg('arg1');

        $this->assertEquals($arg, 'hello');
    }

    public function testGetArgs(): void
    {
        $request = ['arg1' => 'hello', 'arg2' => 'hello world'];

        $main = $this->dummyMain([], $request);
        $args = $main->getArgs();

        $this->assertEquals($args, $request);
    }

    public function testGetUploads(): void
    {
        $main = $this->dummyMain([], [], ['test' => 1]);
        $uploads = $main->getUploads();

        $this->assertEquals(1, $uploads['test']);
     }

    public function testParseUrl(): void
    {
        $env = ['REQUEST_URI' => '/parse-me'];

        $main = $this->dummyMain($env);
        $parsed_url = $main->parseUrl('path');

        $this->assertEquals('/parse-me', $parsed_url);
    }

    public function testGetQueryParams(): void
    {
        $env = ['REQUEST_URI' => '/parse-me?test=1'];

        $main = $this->dummyMain($env);
        $params = $main->getQueryParams();

        $this->assertEquals(['test' => '1'], $params);
    }

    public function testGetRoute(): void
    {
        $env = ['REQUEST_URI' => '/route'];

        $main = $this->dummyMain($env);
        $route = $main->getRoute();

        $this->assertEquals('/route', $route);
    }

    public function testGetMethod(): void
    {
        $env = ['REQUEST_METHOD' => 'GET'];

        $main = $this->dummyMain($env);
        $method = $main->getMethod();

        $this->assertEquals('GET', $method);
    }

    public function testGetHost(): void
    {
        $env = ['HTTP_HOST' => 'example.com'];

        $main = $this->dummyMain($env);
        $host = $main->getHost();

        $this->assertEquals('example.com', $host);
    }

    public function testGetRemoteAddress(): void
    {
        $env = ['REMOTE_ADDR' => '127.0.0.1'];

        $main = $this->dummyMain($env);
        $ip = $main->getRemoteAddress();

        $this->assertEquals('127.0.0.1', $ip);
    }

    public function testGetConfigPath(): void
    {
        $main = $this->dummyMain();
        $conf_path = $main->getConfigPath();

        $this->assertEquals($this->root . 'conf/podsumer.conf', $conf_path);
    }

    public function testGetState(): void
    {
        $main = $this->dummyMain();
        $state = $main->getState();

        $this->assertTrue(is_subclass_of($state::class, State::class));
    }

    public function testGetStateFilePath()
    {
        $main = $this->dummyMain();
        $path = $main->getStateFilePath();
        $this->assertEquals(true, is_String($path));
    }

    protected function dummyMain(array $env = [], array $request = [], array $files = [])
    {
        $env = array_merge([
            'REQUEST_SCHEME' => 'http',
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/',
            'REQUEST_METHOD' => 'GET',
            'REMOTE_ADDR' => '127.0.0.1',
        ], $env);

        $main = new Main($this->root, $env, $request, $files);

        return $main;
    }
}

