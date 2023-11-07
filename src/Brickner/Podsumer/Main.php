<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use function call_user_func;
use function parse_url;
use function getallheaders;
use function filesize;

class Main
{
    protected array $env;
    protected array $args;
    protected array $uploads;
    protected Logs $logs;
    protected State $state;
    protected Config $config;
    protected string $path;

    public function __construct(string $path, array $env, array $request, array $files, bool $test_mode = false)
    {
        $this->env = $env;
        $this->args = $request;
        $this->uploads = $files;

        $this->path = $path;
        $this->config = new Config($this->getConfigPath($test_mode));
        $this->logs = new Logs($this);
        $this->state = new State($this);
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

            $args = $this->getArgs();

            // Sanitize inputs to sidestep XSS. QPs in this app are only alpha-numeric anyway.
            $args = filter_var_array($args,  \FILTER_SANITIZE_FULL_SPECIAL_CHARS);

            call_user_func($route[0], $args, $this);

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

    public function getBaseUrl(): string
    {
        return $this->env['REQUEST_SCHEME']
            . '://'
            . $this->env['HTTP_HOST'];
    }

    public function getArg(string $key): mixed
    {
        return $this->args[$key];
    }

    /**
    * @codeCoverageIgnore
    */
    public function getHeaders(): array
    {
        return getallheaders();
    }

    public function getUploads(): array
    {
        return $this->uploads;
    }

    public function getArgs(): array
    {
        return $this->args;
    }

    public function parseUrl(string $key): mixed
    {
        $url = $this->getUrl();
        $parse = parse_url($url);

        return $parse[$key] ?? null;
    }

    public function getQueryParams(): array
    {
        $q = [];
        $query = $this->parseUrl('query') ?? '';

        parse_str($query, $q);

        return $q;
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

    public function getConfigPath($test_mode = false): string
    {
        return $test_mode
            ? $this->path . 'conf/test.conf'
            : $this->path . 'conf/podsumer.conf';
    }

    public function getConf(string $key1, ?string $key2 = null): mixed
    {
        $f = $this->config->get($key1, $key2);
        return $f;
    }

    public function log(string $message): void
    {
        $this->logs->log($message);
    }

    public function getState(): State
    {
        return $this->state;
    }

    public function getStateFilePath(): string
    {
        return $this->getInstallPath()
            . $this->getConf('podsumer', 'state_file');
    }

    public function getInstallPath(): string
    {
        return $this->path;
    }

    /**
    * @codeCoverageIgnore
    */
    public function getDbSize(): int
    {
        return filesize($this->getStateFilePath());
    }

    /**
    * @codeCoverageIgnore
    */
    public function redirect(string $path)
    {
        header("Location: $path");
    }
}

