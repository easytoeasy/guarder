<?php

namespace pzr\guarder\sender;

use pzr\guarder\BaseObject;

class Message extends BaseObject
{
    public $apikey;
    public $tplid;
    /** @var string */
    public $sendTo;
}