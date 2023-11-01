<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \DateTime;
use \SimpleXMLElement;

class Item
{
    protected Main $main;
    protected SimpleXMLElement $item;
    protected Feed $feed;

    public function __construct(Main $main, SimpleXMLElement $item, Feed $feed)
    {
        $this->main = $main;
        $this->item = $item;
        $this->feed = $feed;
    }

    public function getFeedId(): int
    {
        return $this->feed->getFeedId();
    }

    public function getGuid(): string
    {
        return strval($this->item->guid);
    }

    public function getName(): string
    {
        return strval($this->item->title);
    }

    public function getPublished(): DateTime
    {
        $published = strval($this->item->pubDate);
        $published = $published ?: date('c');

        return new DateTime($published);
    }

    public function getDescription(): string
    {
        return strval($this->item->description);
    }

    public function getSize(): int
    {
        $size = 0;
        $enclosure_attrs = $this->item->enclosure->attributes();
        if (!empty($enclosure_attrs)) {
            $size = intval($enclosure_attrs->length);
        }

        return $size;
    }

    public function getAudioFileUrl(): string
    {
        $media_url = '';
        $enclosure_attrs = $this->item->enclosure->attributes();
        if (!empty($enclosure_attrs)) {
            $media_url = strval($enclosure_attrs->url);
        }

        return $media_url;
    }

    public function getImage(): string|bool
    {
        $image = $this->item->children('itunes', true)->image;
        $href = '';
        if (!empty($image)) {
            $href = strval($image->attributes()->href);
            return $href;
        }

        return false;
    }
}

