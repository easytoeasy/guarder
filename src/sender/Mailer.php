<?php

namespace pzr\guarder\sender;

use pzr\guarder\BaseObject;

class Mailer extends BaseObject
{

    public $host;
    public $username;
    public $password;
    public $port;
    public $from;
    public $name;
    /** @var array */
    public $sendTo;

}