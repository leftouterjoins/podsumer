<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use function parse_ini_file;
use \Exception;

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

    public function set(mixed $value, string $key1, ?string $key2 = null): void
    {
        if (null !== $key2) {
            $this->config[$key1][$key2] = $value;;
        } else {
            $this->config[$key1] = $value;
        }
    }

    protected function parseConfig($path): mixed
    {
        error_clear_last();

        # '@' is used to suppress warnings about parse issues.
        # We will throw an exception if the file is not valid.
        $result = @parse_ini_file(
            $path,
            true,
            INI_SCANNER_TYPED
        );

        if (false === $result) {
           $error = error_get_last();
           throw new Exception('Config file at ' . $path . ' is not valid: ' . ($error['message'] ?? '') . '.');
        }

        return $result;
    }
}

