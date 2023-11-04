<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Brickner\Podsumer\Config;

final class ConfigTest extends TestCase
{
    public string $root = __DIR__ . DIRECTORY_SEPARATOR . '../../..' . DIRECTORY_SEPARATOR;

    public function testConstruct(): void
    {
        $config = new Config($this->root . DIRECTORY_SEPARATOR . 'conf/test.conf');
        $this->assertEquals($config::class, Config::class);
    }

    public function testGet(): void
    {
        $config = new Config($this->root . DIRECTORY_SEPARATOR . 'conf/test.conf');
        $user = $config->get('podsumer', 'user');

        $this->assertEquals($user, 'user');
    }

    public function testGetGroup(): void
    {
        $config = new Config($this->root . DIRECTORY_SEPARATOR . 'conf/test.conf');
        $group = $config->get('podsumer');

        $this->assertEquals(is_array($group), true);
    }

    public function testGetNonExistent(): void
    {
        $config = new Config($this->root . DIRECTORY_SEPARATOR . 'conf/test.conf');
        $empty = $config->get('podsumer', 'non-existent');

        $this->assertEquals($empty, null);
    }

    public function testBadParsePath(): void
    {
        $this->expectException(Exception::class);
        $config = new Config($this->root . DIRECTORY_SEPARATOR . 'conf/bad.txt');
    }

    public function testParseExecption(): void
    {
        $this->expectException(Exception::class);
        $config = new Config($this->root . DIRECTORY_SEPARATOR . 'conf/test_bad.conf');
    }

    public function testEmptyConfig(): void
    {
        $this->expectException(Exception::class);
        $config = new Config($this->root . DIRECTORY_SEPARATOR . 'conf/test_empty.conf');
    }

}

