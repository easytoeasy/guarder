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

    public static $senderClassMap = [
        self::YUNPIAN   => Yunpian::class,
        self::MAILER    => Mailer::class,
    ];

    public static function getClasses(string $typeStr)
    {
        if (empty($typeStr))
            return false;
        $types = explode(',', $typeStr);
        if (in_array(self::FANOUT, $types))
            return array_values(self::$senderClassMap);

        $class = [];
        foreach($types as $type) {
            if (!isset(self::$senderClassMap[$type])) continue;
            $class[] = self::$senderClassMap[$type];
        }
        return $class;
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