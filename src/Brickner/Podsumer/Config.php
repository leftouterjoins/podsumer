<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use function parse_ini_file;

class Config
{

    protected string $path;
    protected array $config = [];

    public function __construct(string $config_path)
    {
        $this->path = $config_path;

        if (!file_exists($this->path)) {
            throw new \Exception('Config file not found at ' . $this->path . '.');
        }

        $parsed = $this->parseConfig($this->path);
        if (false === $parsed) {
            throw new \Exception('Config file at ' . $this->path . ' is not valid.');
        }

        $this->config = $parsed;

        if (empty($this->config)) {
            throw new \Exception('Config file at ' . $this->path . ' is empty.');
        }
    }

    public function get(string $key1, ?string $key2 = null): mixed
    {
        if (null !== $key2) {
            return $this->config[$key1][$key2] ?? null;
        } else {
            return $this->config[$key1] ?? null;
        }
    }

    protected function parseConfig($path): mixed
    {
        return parse_ini_file(
            $path,
            true,
            INI_SCANNER_TYPED
        );
    }
}

