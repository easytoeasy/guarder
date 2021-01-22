<?php

namespace pzr\guarder\sender;

use ErrorException;
use Monolog\Logger;
use pzr\guarder\BaseObject;
use pzr\guarder\Helper;
use pzr\guarder\IniParser;

abstract class Message extends BaseObject
{

    /** @var string 待发送主体 */
    protected $subject;
    /** @var string 待发送内容 */
    protected $content;
    /** @var string 对应ini中的配置模块 */
    protected $module;
    /** @var Logger */
    protected $logger;
    /**  */
    protected $receiver;

    /** 发送通知 */
    abstract public function send();

    /** 校验是否符合条件 */
    abstract public function check(): bool;

    public function init()
    {
        $this->logger = Helper::getLogger($this->getModule());
        $config = $this->getModuleConfig();
        if (empty($config))
            throw new ErrorException(sprintf("ini module:%s failed", $this->getModule()));
        Helper::configure($this, $config);
    }

    public function getModuleConfig()
    {
        $ini = IniParser::parseReceiver();
        $module = $this->getModule();
        if (empty($ini) || empty($module)) {
            $this->logger->error('receiver ini or module is empty');
            return false;
        }
        if (!isset($ini[$module])) {
            $this->logger->error(sprintf("Failed loaded module:%s in receiver ini", $module));
            return false;
        }
        return $ini[$module];
    }


    /**
     * Get the value of content
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Set the value of content
     *
     * @return  self
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get the value of subject
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * Set the value of subject
     *
     * @return  self
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Get the value of module
     */
    public function getModule()
    {
        return $this->module;
    }

    /**
     * Set the value of module
     *
     * @return  self
     */
    public function setModule($module)
    {
        $this->module = $module;

        return $this;
    }

    /**
     * @return Receiver
     */ 
    public function getReceiver()
    {
        return $this->receiver;
    }

    /**
     * Set the value of receiver
     *
     * @return  self
     */ 
    public function setReceiver($receiver)
    {
        $this->receiver = $receiver;

        return $this;
    }
}
