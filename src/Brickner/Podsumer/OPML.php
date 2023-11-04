<?php declare(strict_types = 1);

namespace Brickner\Podsumer;

use \simplexml_load_file;
use \SimpleXMLElement;

class OPML
{
    protected array $file;

    public function __construct(array $file)
    {
        $this->file = $file;
    }

    public static function parse(array $file): array
    {
        $opml = new self($file);

        return $opml->getFeeds();
    }

    protected function getFeeds(): array
    {
        $opml = simplexml_load_file($this->file['tmp_name']);
        $body = $opml->body->outline;

        if (count($body) < 2) {
            $body = $body->outline;
        }

        $feeds = [];
        foreach ($body as $feed) {
            $feeds[] = strval($feed->attributes()->xmlUrl);
        }

        return $feeds;
    }
}
