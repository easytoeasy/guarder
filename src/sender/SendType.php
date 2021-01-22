<?php

namespace pzr\guarder\sender;


class SendType
{

    /** 通知方式 */
    const NOSEND    = 0;
    const MAILER    = 1;
    const YUNPIAN   = 2;
    const WEIXIN    = 3;

    const FANOUT    = 100;

    /**
     * @return array
     */
    public static function getSenderClassMap()
    {
        return [
            self::YUNPIAN   => Yunpian::class,
            self::MAILER    => Mailer::class,
        ];
    }

    public static function getSendTypes()
    {
        return [
            self::NOSEND,
            self::MAILER,
            self::YUNPIAN,
            self::WEIXIN,
            self::FANOUT,
        ];
    }

}