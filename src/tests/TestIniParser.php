<?php  declare(strict_types=1);

namespace pzr\tests;

use PHPUnit\Framework\TestCase;
use pzr\guarder\Config;
use pzr\guarder\IniParser;
use pzr\guarder\sender\Sender;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/vendor/yiisoft/yii2/Yii.php';

final class TestIniParser extends TestCase
{

    public function testParser()
    {
        $config = IniParser::parse();
        $this->assertInstanceOf(Config::class, $config);
        $this->assertObjectHasAttribute('taskers', $config);
        $this->assertNotEmpty($config->taskers);
        // var_export($config);
    }

    public function testSender()
    {
        $sender = new Sender();
        $this->assertFileExists($sender->ini_file);
        // var_export($sender->init());
        // $sender->send('mynames', 'test');
    }

}