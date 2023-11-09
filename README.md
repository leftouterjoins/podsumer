[![Unit Tests](https://github.com/joshwbrick/podsumer/actions/workflows/php.yml/badge.svg)](https://github.com/joshwbrick/podsumer/actions/workflows/php.yml)
[![Unit Test Coverage](https://raw.githubusercontent.com/joshwbrick/podsumer/image-data/coverage.svg)](https://github.com/joshwbrick/podsumer/actions/workflows/php.yml)

# Features
 - Self hostable
    - Single Tenant SQLite backend.
    - Run from a single docker image
 - Privacy oriented
    - No data or usage collection
    - Proxy and cache podcast feeds to reduce your traffic to data collecting feed and media servers.
 - Supports streaming audio files (e.g. seek to any part of a podcast)
 - OPML Import & Export
   - Import your current subscriptions from another app
   - Export subscriptions to podsumer feeds for use in your mobile or other podcast app.
 - Single file library
 - Automatic feed refresh
    - Original feeds checked for updates when proxied feeds are queried.
 - Codebase contains zero 3rd-party dependencies.
 - Content focused UI

# Roadmap

For a look at upcoming enhancements checkout the enhancement tag under issues tab.

# Usage

This project is useful for self-hosters who listen to podcasts. It allows you to listen to your podcasts via the web on your own infrastructure. You can also use the OPML export to subscribe to Podsumer's mirror of the original feeds. This improves privacy as download and listening metrics will not be tied to your phone or personal computer. Using something like gluetun you can have all the traffic to podcast servers go through a VPN as well, further shrinking your digital footprint.

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

# Screenshots

### Home Page / Feed List
[![Feed](https://raw.githubusercontent.com/joshwbrick/podsumer/development/screenshots/feeds.png)](https://github.com/joshwbrick/podsumer/development/screenshots/feeds.png)

### Episode List
[![Episodes](https://raw.githubusercontent.com/joshwbrick/podsumer/development/screenshots/feed.png)](https://raw.githubusercontent.com/joshwbrick/podsumer/development/screenshots/feed.png)

### Player Page
[![Player](https://raw.githubusercontent.com/joshwbrick/podsumer/development/screenshots/episode.png)](https://raw.githubusercontent.com/joshwbrick/podsumer/development/screenshots/episode.png)
