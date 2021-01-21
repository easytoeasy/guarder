<?php

namespace pzr\guarder\sender;

use pzr\guarder\BaseObject;

class Message extends BaseObject
{
    /** @var string 短信的apikey */
    public $apikey;
    /** @var string 短信的模板ID */
    public $tplid;
    /** @var string 短信模板的占位符 */
    public $tplstr;
    /** @var string */
    public $sendTo;
}