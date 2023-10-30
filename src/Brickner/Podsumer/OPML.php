<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \simplexml_load_file;
use \SimpleXMLElement;

class OPML
{
    private Main $main;
    private array $file;

    public function __construct(Main $main, array $file)
    {
        $this->main = $main;
        $this->file = $file;
    }

    public static function parse(Main $main, array $file)
    {
        $opml = new self($main, $file);

        return $opml->getFeeds();
    }

    protected function getFeeds(): array
    {
        $opml = simplexml_load_file($this->file['tmp_name']);
        $feeds = [];
        foreach ($opml->body->outline as $feed) {
            $feeds[] = strval($feed->attributes()->xmlUrl);
        }

        return $feeds;
    }
}
