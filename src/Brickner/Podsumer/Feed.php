<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \Exception;
use \PHP_URL_SCHEME;
use \LIBXML_NOCDATA;
use \simplexml_load_file;
use \SimpleXMLElement;

class Feed
{
    private Main $main;
    private string $url;
    private string $hash;
    private string $feed_path;
    private SimpleXMLElement $feed;

    public function __construct(Main $main, string $url)
    {
        $this->main = $main;

        $valid_url = $this->validateUrl($url);
        if (false === $valid_url) {
            throw new Exception("Invalid feed URL: $url");
        }

        $this->url = $url;

        $this->feed_path = $this->main->getState()->getStateDirPath()
            . DIRECTORY_SEPARATOR
            . $this->getUrlHash()
            . '.xml';

        $this->fetchFeed();
    }

    private function fetchFeed()
    {
        $feed_contents = file_get_contents($this->url);
        if (false === $feed_contents) {
            throw new Exception("Cannot fetch feed URL: $url");
        }

        $this->hash = md5($feed_contents);

        if (!file_exists($this->feed_path)) {
            $written = file_put_contents($this->feed_path, $feed_contents);
            if (false === $written) {
                throw new Exception("Cannot write feed to path: $this->feed_path");
            }
        } else {
            $this->main->log('Feed already downloaded.');
        }

        $this->parseFeed();
     }

    private function validateUrl(string $url)
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (false === $scheme || is_null($scheme) || !str_contains($scheme, 'http')) {
            return false;
        }

        return true;
    }

    private function parseFeed(): SimpleXMLElement|false
    {
        $this->feed = simplexml_load_file($this->feed_path, 'SimpleXMLElement', LIBXML_NOCDATA);
        return $this->feed;
    }

    public function getTitle(): string
    {
        return strval($this->feed->channel->title);
    }

    public function getPubDate  (): int
    {
        $lastUpdated = $this->feed->channel->lastBuildDate
            ?? $this->getItems()[0]['pubDate'];

        return strtotime(strval($lastUpdated));
    }

    public function getDescription(): string
    {
        return strval($this->feed->channel->description);
    }

    public function getChannelArt(): string
    {
        return strval($this->feed->channel->image->url);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getUrlHash(): string
    {
        return md5($this->url);
    }

    public function getId(): string
    {
        return $this->hash;
    }

    public function getItems(): array
    {
        $items = [];

        foreach ($this->feed->channel->item as $item) {

            $image = $item->children('itunes', true)->image;
            if (!empty($image)) {
                $image = strval($image->attributes()->href);
            }

            $media_url = null;
            $enclosure_attrs = $item->enclosure->attributes();
            if (!empty($enclosure_attrs)) {
                $media_url = strval($enclosure_attrs->url);
                $length = strval($enclosure_attrs->length);
            }

            $item_data = [
                'title' => strval($item->title),
                'pubDate' => strval($item->pubDate),
                'audio_url' => $media_url,
                'length' => $length,
                'description' => strval($item->description),
                'art_url' => $image,
            ];
            $items[] = $item_data;
        }

        return $items;
    }
}
