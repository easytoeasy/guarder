<?php

namespace pzr\guarder;

use ErrorException;

class Config extends BaseObject
{
    public $user;
    public $passwd;
    public $host;
    public $port;
    public $files;
    public $taskers = array();
    public $ppid_file;
    public $logfile;
    public $receiver;

    public function check()
    {
        if (empty($this->host) || empty($this->port)) {
            throw new ErrorException(sprintf(
                "host:%s or port:%s is empty",
                $this->host,
                $this->port
            ));
        }

        if (empty($this->taskers)) {
            throw new ErrorException(sprintf("taskers is empty"));
        }

        return true;
    }
}
