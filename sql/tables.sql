CREATE TABLE IF NOT EXISTS `feeds` (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url_hash TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    last_update DATETIME NOT NULL,
    url TEXT NOT NULL,
    description TEXT,
    image BLOB
);

CREATE TABLE IF NOT EXISTS `items` (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    feed_id INTEGER NOT NULL,
    name TEXT NOT NULL,
    published DATETIME NOT NULL,
    description TEXT,
    size INTEGER NOT NULL,
    audio_url TEXT NOT NULL,
    image BLOB
);

