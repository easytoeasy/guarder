<?php  declare(strict_types=1);

namespace pzr\tests;

use PHPUnit\Framework\TestCase;
use pzr\guarder\Config;
use pzr\guarder\IniParser;
use pzr\guarder\sender\Sender;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

final class TestIniParser extends TestCase
{

    public function testParser()
    {
        $file = IniParser::getReceiverFile();
        $this->assertFileExists($file);
        $ini = IniParser::parseReceiver();
        var_export($ini);
    }

    public function testSenderMailer()
    {
        // $program = 'common';
        // $program = 'serv_state';
        $program = 'sztcgw';
        $sender = new Sender();
        $sender->setProgram($program);
        // $sender->send('mynames', 'test');
    }

}