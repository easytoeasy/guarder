<?php

namespace pzr\guarder\sender;

use Monolog\Logger;
use pzr\guarder\BaseObject;
use pzr\guarder\Helper;
use pzr\guarder\IniParser;

class Sender extends BaseObject
{

    /** @var array */
    protected $ini;
    /** @var string 子任务对应的program值 */
    protected $program;
    /** @var Logger */
    protected $logger;

    public function init()
    {
        $this->logger = Helper::getLogger('Sender', $file = '', Logger::DEBUG);
        $this->ini = IniParser::parseReceiver();
        $this->check();
    }

    public function send($content, $subject)
    {
        $receiver = null;
        if (isset($this->ini[$this->getProgram()])) {
            $receiver = new Receiver($this->ini[$this->getProgram()]);
        }
        if (empty($receiver)) {
            $this->logger->error(
                sprintf("program:%s to receiver failed", $this->getProgram())
            );
            return false;
        }
        $sendType = $receiver->getSend_type();
        $classes = SendType::getClasses($sendType);
        if (empty($classes)) {
            $this->logger->debug(
                sprintf("program:%s to sendClass failed", $this->getProgram())
            );
            return false;
        }
        foreach ($classes as $class) {
            $class = new $class();
            if ($class instanceof Message) {
                $class->setContent($content)
                    ->setSubject($subject)
                    ->setReceiver($receiver)
                    ->send();
            }
        }
    }

    public function check(): bool
    {
        if (empty($this->ini)) {
            $this->logger->error('invalid value of receiver.ini');
            return false;
        }
        return true;
    }

    /**
     * Get the value of program
     */
    public function getProgram()
    {
        if (empty($this->program))
            $this->program = 'common';
        return $this->program;
    }

    /**
     * Set the value of program
     *
     * @return  self
     */
    public function setProgram($program)
    {
        $this->program = $program;

        return $this;
    }

    /**
     * Get the value of ini
     */
    public function getIni()
    {
        return $this->ini;
    }
}
