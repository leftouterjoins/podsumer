<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use function parse_ini_file;

class Config
{

    private string $path;
    private array $config = [];

    public function __construct(Main $main)
    {
        $this->path = $main->getConfigPath();

        if (!file_exists($this->path)) {
            throw new \Exception('Config file not found at ' . $this->path . '.');
        }

        $this->config = $this->parseConfig($this->path);
        if (false === $this->config) {
            throw new \Exception('Config file at ' . $this->path . ' is not valid.');
        }

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

    public function set(mixed $value, string $key1, ?string $key2 = null): void
    {
        if (null !== $key2) {
            $this->config[$key1][$key2] = $value;
        } else {
            $this->config[$key1] = $value;
        }
    }

    private function parseConfig($path): mixed
    {
        return parse_ini_file(
            $path,
            true,
            INI_SCANNER_TYPED
        );
    }
}

