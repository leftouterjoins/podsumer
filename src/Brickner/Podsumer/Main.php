<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use function call_user_func;

class Main
{
    private $env;
    private $args;
    private $uploads;
    private Logs $logs;
    private State $state;
    private Config $config;
    private $path;

    public function __construct($path)
    {
        $this->env = $_ENV;
        $this->args = $_REQUEST;
        $this->uploads = $_FILES;

        $this->path = $path;
        $this->config = new Config($this);
        $this->logs = new Logs($this);
        $this->state = new State($this);

        unset($_GET);
        unset($_FILES);
        unset($_POST);
        unset($_COOKIE);
        unset($_REQUEST);
        unset($_ENV);
        unset($_SERVER);
    }

    public function run(): void
    {
        $route = (new Route(
            $this->getRoute(),
            $this->getMethod(),
            $this->getQueryParams(),
        ))->matchedRoute;

        if (empty($route)) {
            $this->setResponseCode(404);
            $this->logs->accessLog();
            return;
        }

        try {
            call_user_func($route[0], $this->getArgs(), $this);
            $this->logs->accessLog();
        } catch (\Exception $e) {

            $this->setResponseCode(500);

            $this->logs->accessLog();
            $this->logs->exceptionLog($e);

            echo $e->getMessage();
        }
    }

    public function getUrl(): string
    {
        return $this->env['REQUEST_SCHEME']
            . '://'
            . $this->env['HTTP_HOST']
            . $this->env['REQUEST_URI'];
    }

    public function getArg(string $key): mixed
    {
        return $this->args[$key];
    }

    public function getUploads(): array
    {
        return $this->uploads;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function getQueryParams(): array
    {
        $q = [];
        $query = $this->parseUrl('query') ?? '';

        parse_str($query, $q);

        return $q;
    }

    public function parseUrl(string $key): mixed
    {
        $url = $this->getUrl();
        $parse = parse_url($url);

        return $parse[$key] ?? null;
    }

    public function getRoute(): string
    {
        return $this->parseUrl('path');
    }

    public function getMethod(): string
    {
        return $this->env['REQUEST_METHOD'];
    }

    public function setResponseCode(int $code): void
    {
        http_response_code($code);
    }

    public function getResponseCode(): int
    {
        return http_response_code();
    }

    public function getHost(): string
    {
        return $this->env['HTTP_HOST'];
    }

    public function getRemoteAddress(): string
    {
        return $this->env['REMOTE_ADDR'];
    }

    public function getConfigPath(): string
    {
        return $this->path . 'conf/podsumer.conf';
    }

    public function getConf(string $key1, ?string $key2 = null): mixed
    {
        $f = $this->config->get($key1, $key2);
        return $f;
    }

    public function setConf(mixed $value, string $key1, ?string $key2 = null): void
    {
        $this->config->set($value, $key1, $key2);
    }

    public function log(string $message): void
    {
        $this->logs->log($message);
    }

    public function getState(): State
    {
        return $this->state;
    }

    public function getInstallPath(): string
    {
        return $this->path;
    }
}

