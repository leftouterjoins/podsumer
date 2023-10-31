<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \Exception;
use \DateTime;
use \PHP_URL_SCHEME;
use \LIBXML_NOCDATA;
use \simplexml_load_file;
use \SimpleXMLElement;

class Feed
{
    private Main $main;
    private string $url;
    private string $hash;
    private int $feed_id;
    private ?SimpleXMLElement $feed;

    public function __construct(Main $main, string $url)
    {
        $this->main = $main;

        $valid_url = $this->validateUrl($url);

        if (false === $valid_url) {
            throw new Exception("Invalid feed URL: $url");
        }

        $this->url = $url;

        $this->fetchFeed();
    }

    private function fetchFeed()
    {
        $feed_contents = File::downloadUrl($this->url);
        $this->hash = md5($feed_contents);
        $this->parseFeed($feed_contents);
     }

    private function validateUrl(string $url)
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (false === $scheme || is_null($scheme) || !str_contains($scheme, 'http')) {
            return false;
        }

        return true;
    }

    private function parseFeed(string $feed_contents): void
    {
         $parse_result = simplexml_load_string(
            $feed_contents,
            SimpleXMLElement::class,
            LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET
        );

        if (false === $parse_result) {
            $this->feed = null;
            return;
        }

        $this->feed = $parse_result;
    }

    public function feedLoaded(): bool
    {
        return !empty($this->feed);
    }

    public function getTitle(): string
    {
        return strval($this->feed->channel->title);
    }

    public function getLastUpdated(): DateTime
    {
        $lastUpdated = strval($this->feed->channel->lastBuildDate);
        $lastUpdated = $lastUpdated ?: date('r');

        return new DateTime($lastUpdated);
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

    public function getFeedItems(): SimpleXMLElement
    {
        return $this->feed->channel->item;
    }

    public function setFeedId(int $feed_id)
    {
        $this->feed_id = $feed_id;
    }

    public function getFeedId(): int
    {
        return $this->feed_id;
    }
}

