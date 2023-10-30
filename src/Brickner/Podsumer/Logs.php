<?php declare(strict_types = 1);

namespace Brickner\Podsumer;
use resource;
use function fputcsv;

class Logs
{

    private $main;
    private $stdout;
    private $stderr;

    public function __construct(Main $main)
    {
        $this->main = $main;
        $this->stdout = fopen('php://stdout', 'w');
        $this->stderr = fopen('php://stderr', 'w');
    }

    public function __destruct()
    {
        fclose($this->stdout);
        fclose($this->stderr);
    }

    public function log(?string $message): void
    {
        $log = [
            'class' => 'info',
            'time' => date('Y-m-d H:i:s'),
            'host' => $this->main->getHost(),
            'method' => $this->main->getMethod(),
            'response_code' => $this->main->getResponseCode(),
            'url' => $this->main->getUrl(),
            'ip' => $this->main->getRemoteAddress(),
            'args' => json_encode($this->main->getArgs()),
            'message' => $message
        ];

        $this->writeLog($log);
    }

    public function accessLog(): void
    {
        $log = [
            'class' => 'access',
            'time' => date('Y-m-d H:i:s'),
            'host' => $this->main->getHost(),
            'method' => $this->main->getMethod(),
            'response_code' => $this->main->getResponseCode(),
            'url' => $this->main->getUrl(),
            'ip' => $this->main->getRemoteAddress(),
            'args' => json_encode($this->main->getArgs())
        ];

        $this->writeLog($log);
    }

    public function exceptionLog(?\Exception $exception): void
    {
        $log = [
            'class' => 'exception',
            'time' => date('Y-m-d H:i:s'),
            'host' => $this->main->getHost(),
            'method' => $this->main->getMethod(),
            'response_code' => $this->main->getResponseCode(),
            'url' => $this->main->getUrl(),
            'ip' => $this->main->getRemoteAddress(),
            'args' => json_encode($this->main->getArgs()),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => str_replace("\n", "\t", $exception->getTraceAsString())
        ];

        $this->writeError($log);
    }

    private function writeLog(array $message): void
    {
        $this->write($this->stdout, $message);
    }

    private function writeError(array $message): void
    {
        $this->write($this->stdout, $message);
    }

    private function write($f, array $message): void
    {
        if (!fputcsv($f, $message)) {
            throw new \Exception('Failed to write to log.');
        }
    }
}

