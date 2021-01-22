<?php

namespace pzr\guarder\sender;

use pzr\guarder\BaseObject;

class Receiver extends BaseObject
{
    public $mailers;
    public $phones;
    public $send_type;

    public function check()
    {
        if (!in_array($this->send_type, SendType::getSendTypes())) {
            $this->send_type = SendType::NOSEND;
        }
    }

    /**
     * Get the value of send_type
     */ 
    public function getSend_type()
    {
        if (!in_array($this->send_type, SendType::getSendTypes())) {
            $this->send_type = SendType::NOSEND;
        }
        return $this->send_type;
    }

    /**
     * Set the value of send_type
     *
     * @return  self
     */ 
    public function setSend_type($send_type)
    {
        $this->send_type = $send_type;

        return $this;
    }
}