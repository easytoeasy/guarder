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
        $this->logger = Helper::getLogger('Sender', __DIR__ . '/send.log', Logger::DEBUG);
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
            $this->logger->error('Failed instance of Receiver class');
            return false;
        }
        $sendType = $receiver->getSend_type();
        $this->logger->debug('sendType:' . $sendType);
        if (empty($sendType)) return false;
        $sendTypes = explode(',', $sendType);
        $classMap = SendType::getSenderClassMap();
        foreach ($sendTypes as $type) {
            if (empty($type)) continue;
            $class = isset($classMap[$type]) ? $classMap[$type] : '';
            $this->logger->debug('send class:' . $class);
            if (empty($class)) continue;
            $class = new $class();
            if ($class instanceof Message) {
                $this->logger->debug('start sending message...');
                $class->setContent($content)
                    ->setSubject($subject)
                    ->setReceiver($receiver)
                    ->send();
            } else {
                $this->logger->error(sprintf("%s is not instance of Message class", get_class($class)));
            }
        }
    }

    public function check(): bool
    {
        if (empty($this->ini)) {
            $this->logger->error('receiver ini is empty');
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
