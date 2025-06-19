<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \Exception;

trait TStateSchemaMigrations
{
    private int $cur_version;

    private array $versions = [ # ORDER IS IMPORTANT
        'create',
        'addImageUrl',
        'addPlaybackPosition',
        'addTranscriptAndAdSections',
        'addJobsTable',
        'updateJobsTableConstraints',
        'addJobsLogColumn',
        'removeJobsProgressColumn'
    ];

    protected function checkDBVersion()
    {
        $this->cur_version = intval($this->query('SELECT MAX(version) AS version FROM versions')[0]['version']) ?? 0;

        while (State::VERSION > $this->cur_version) {
            $new_version = $this->cur_version + 1;
            $upgradeFunc = $this->versions[$new_version] ?? null;

            if (!is_null($upgradeFunc) && $result = $this->$upgradeFunc()) {

                $updated = $this->query("INSERT INTO versions (version) VALUES ($new_version)");

                //@codeCoverageIgnoreStart
                if (false === $updated) {
                    throw new Exception("Could not set new DB version.");
                    break;
                }
                //@codeCoverageIgnoreEnd

                $this->cur_version = $new_version;
            } else {
                //@codeCoverageIgnoreStart
                throw new Exception("Could not upgrade DB");
                //@codeCoverageIgnoreEnd
            }
        }
    }

    public function addImageUrl(): bool {
        $addFeedImageUrl = $this->query("ALTER TABLE `feeds` ADD COLUMN image_url");
        $addItemImageUrl = $this->query("ALTER TABLE `items` ADD COLUMN image_url");
        
        return $addFeedImageUrl !== false && $addItemImageUrl !== false;
    }

    public function addPlaybackPosition(): bool
    {
        $addPlayback = $this->query("ALTER TABLE `items` ADD COLUMN playback_position INTEGER DEFAULT 0");
        return $addPlayback !== false;
    }

    public function addTranscriptAndAdSections(): bool {
        $addTranscript = $this->query("ALTER TABLE `items` ADD COLUMN transcript TEXT");
        $addAdSections = $this->query("ALTER TABLE `items` ADD COLUMN ad_sections TEXT");
        
        return $addTranscript !== false && $addAdSections !== false;
    }

    public function addJobsTable(): bool {
        $createJobsTable = $this->query("
            CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL CHECK(type IN ('refresh_feed', 'download_item', 'process_ads')),
                feed_id INTEGER,
                item_id INTEGER,
                status TEXT NOT NULL CHECK(status IN ('queued', 'running', 'completed', 'failed', 'cancelled')) DEFAULT 'queued',
                pid INTEGER,
                started_at DATETIME,
                finished_at DATETIME,
                progress INTEGER DEFAULT 0 CHECK(progress >= 0 AND progress <= 100),
                error TEXT,
                openai_cost REAL DEFAULT 0.0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE CASCADE,
                FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
            )
        ");
        
        $createJobsIndex = $this->query("CREATE INDEX idx_jobs_status ON jobs(status)");
        $createJobsTypeIndex = $this->query("CREATE INDEX idx_jobs_type_feed_item ON jobs(type, feed_id, item_id)");
        
        return $createJobsTable !== false && $createJobsIndex !== false && $createJobsTypeIndex !== false;
    }

    public function updateJobsTableConstraints(): bool {
        // Cancel any existing refresh_all jobs
        $cancelRefreshAll = $this->query("UPDATE jobs SET status = 'cancelled', finished_at = datetime('now') WHERE type = 'refresh_all' AND status IN ('queued', 'running')");
        
        // SQLite doesn't support modifying constraints directly, so we need to recreate the table
        // First, create a backup of the data (excluding refresh_all jobs)
        $backupData = $this->query("SELECT * FROM jobs WHERE type != 'refresh_all'");
        
        // Drop the old table
        $dropTable = $this->query("DROP TABLE jobs");
        
        // Recreate the table with new constraints
        $createJobsTable = $this->query("
            CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL CHECK(type IN ('refresh_feed', 'download_item', 'process_ads')),
                feed_id INTEGER,
                item_id INTEGER,
                status TEXT NOT NULL CHECK(status IN ('queued', 'running', 'completed', 'failed', 'cancelled')) DEFAULT 'queued',
                pid INTEGER,
                started_at DATETIME,
                finished_at DATETIME,
                progress INTEGER DEFAULT 0 CHECK(progress >= 0 AND progress <= 100),
                error TEXT,
                openai_cost REAL DEFAULT 0.0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE CASCADE,
                FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
            )
        ");
        
        // Restore the data (excluding refresh_all jobs)
        if ($backupData && is_array($backupData)) {
            foreach ($backupData as $row) {
                if ($row['type'] !== 'refresh_all') {
                    $this->query("INSERT INTO jobs (id, type, feed_id, item_id, status, pid, started_at, finished_at, progress, error, openai_cost, created_at)
                                 VALUES (:id, :type, :feed_id, :item_id, :status, :pid, :started_at, :finished_at, :progress, :error, :openai_cost, :created_at)", $row);
                }
            }
        }
        
        // Recreate indexes
        $createJobsIndex = $this->query("CREATE INDEX idx_jobs_status ON jobs(status)");
        $createJobsTypeIndex = $this->query("CREATE INDEX idx_jobs_type_feed_item ON jobs(type, feed_id, item_id)");
        
        return $dropTable !== false && $createJobsTable !== false && $createJobsIndex !== false && $createJobsTypeIndex !== false;
    }

    public function addJobsLogColumn(): bool {
        $addLogColumn = $this->query("ALTER TABLE jobs ADD COLUMN log TEXT");
        return $addLogColumn !== false;
    }

    public function removeJobsProgressColumn(): bool {
        // SQLite doesn't support dropping columns directly, so we need to recreate the table
        // First, backup the data
        $backupData = $this->query("SELECT id, type, feed_id, item_id, status, pid, started_at, finished_at, error, openai_cost, created_at, log FROM jobs");
        
        // Drop the old table
        $dropTable = $this->query("DROP TABLE jobs");
        
        // Recreate the table without the progress column
        $createJobsTable = $this->query("
            CREATE TABLE jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL CHECK(type IN ('refresh_feed', 'download_item', 'process_ads')),
                feed_id INTEGER,
                item_id INTEGER,
                status TEXT NOT NULL CHECK(status IN ('queued', 'running', 'completed', 'failed', 'cancelled')) DEFAULT 'queued',
                pid INTEGER,
                started_at DATETIME,
                finished_at DATETIME,
                error TEXT,
                openai_cost REAL DEFAULT 0.0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                log TEXT,
                FOREIGN KEY (feed_id) REFERENCES feeds(id) ON DELETE CASCADE,
                FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
            )
        ");
        
        // Restore the data
        if ($backupData && is_array($backupData)) {
            foreach ($backupData as $row) {
                $this->query("INSERT INTO jobs (id, type, feed_id, item_id, status, pid, started_at, finished_at, error, openai_cost, created_at, log) 
                             VALUES (:id, :type, :feed_id, :item_id, :status, :pid, :started_at, :finished_at, :error, :openai_cost, :created_at, :log)", $row);
            }
        }
        
        // Recreate indexes
        $createJobsIndex = $this->query("CREATE INDEX idx_jobs_status ON jobs(status)");
        $createJobsTypeIndex = $this->query("CREATE INDEX idx_jobs_type_feed_item ON jobs(type, feed_id, item_id)");
        
        return $dropTable !== false && $createJobsTable !== false && $createJobsIndex !== false && $createJobsTypeIndex !== false;
    }
}

