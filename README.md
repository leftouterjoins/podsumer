cd [![Unit Tests](https://github.com/joshwbrick/podsumer/actions/workflows/php.yml/badge.svg)](https://github.com/joshwbrick/podsumer/actions/workflows/php.yml)
[![Unit Test Coverage](https://raw.githubusercontent.com/joshwbrick/podsumer/image-data/coverage.svg)](https://github.com/joshwbrick/podsumer/actions/workflows/php.yml)

# Features
 - Self hostable
    - single user
    - Docker Image
    - Docker Compose
 - Privacy oriented
    - No data or usage collection
    - Proxy and cache podcast feeds to reduce traffic to data collecting feed and media servers
 - Support for streaming audio
 - Export OPML of proxied podcast feed
 - Single file library
    - sqlite DB
 - Automatic feed refresh
    - Original feeds checked for updates when proxied feeds are queried.

# Usage

## Installing with Docker Compose

The docker image is based on the official PHP Debian Bookworm with Apache image.

```
  podsumer:
    image: ghcr.io/joshwbrick/podsumer:latest
    container_name: podsumer
    volumes:
        /path/to/dir/for/db:/opt/podsumer/store
        /path/to/config/podsumer.conf:/opt/podsumer/conf/podsumer.conf
    ports:
      - 3094:3094
```

# Requirements

 - PHP 8.2+ w/ extensions:
     - simplexml
     - curl
     - finfo
     - PDO
 - Composer
 - SQLite 3.6.19+
     - Foreign key support is required.
 - PHP-FPM & Web server

# @TODO

 - complete remaining unit tests
 - remember play position
 - remember if podcast finished
 - improve player UI
 - Add UI for feed auth
 - search
 - index search and add
 - pagination on feed page
 - offline support?
 - Stanadalone apps?

