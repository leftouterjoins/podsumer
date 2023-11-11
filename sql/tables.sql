CREATE TABLE IF NOT EXISTS `file_contents` (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    content_hash TEXT NOT NULL UNIQUE,
    data BLOB
);

CREATE TABLE IF NOT EXISTS `files` (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT NOT NULL,
    url_hash TEXT NOT NULL UNIQUE,
    filename TEXT NOT NULL,
    cached DATETIME NOT NULL,
    size INTEGER NOT NULL,
    mimetype TEXT NOT NULL,
    content_hash TEXT NOT NULL,
    content_id INTEGER NOT NULL,
    storage_mode TEXT CHECK(storage_mode IN ('DB','DISK')) NOT NULL DEFAULT 'DB',
    CONSTRAINT one FOREIGN KEY (content_id) REFERENCES file_contents(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `feeds` (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url_hash TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    last_update DATETIME NOT NULL,
    url TEXT NOT NULL,
    description TEXT,
    image_url TEXT,
    image INTEGER
);

CREATE TABLE IF NOT EXISTS `items` (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    guid TEXT UNIQUE,
    feed_id INTEGER NOT NULL REFERENCES feeds(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    published DATETIME NOT NULL,
    description TEXT,
    size INTEGER NOT NULL,
    audio_url TEXT NOT NULL,
    audio_file INTEGER NULL,
    image_url TEXT NULL,
    image INTEGER NULL
);

