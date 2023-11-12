<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \Exception;

trait TStateSchemaMigrations
{
    CONST VERSION = 1;

    private int $cur_version;

    private array $versions = [ # ORDER IS IMPORTANT
        'create',
        'addDiskStorage'
    ];

    protected function checkDBVersion()
    {
        $this->cur_version = intval($this->query('SELECT MAX(version) AS version FROM versions')[0]['version']) ?? 0;

        while (self::VERSION > $this->cur_version) {
            $new_version = $this->cur_version + 1;
            $upgradeFunc = $this->versions[$new_version];

            if ($this->$upgradeFunc()) {
                $updated = $this->query("INSERT INTO versions (version) VALUES ($new_version)");

                if (false === $updated) {
                    throw new Exception("Could set new DB version.");
                    break;
                }

                $this->cur_version = $new_version;
            } else {
                throw new Exception("Could not upgrade DB");
            }
        }
    }

    public function addDiskStorage(): bool {

        $addStorageMode = $this->query("ALTER TABLE `files` ADD COLUMN storage_mode TEXT CHECK(storage_mode IN ('DB','DISK')) NOT NULL DEFAULT 'DB'");
        $addFeedImageUrl = $this->query("ALTER TABLE `feeds` ADD COLUMN image_url");
        $addItemImageUrl = $this->query("ALTER TABLE `items` ADD COLUMN image_url");

        return $addStorageMode !== false && $addFeedImageUrl !== false && $addItemImageUrl !== false;
    }
}

