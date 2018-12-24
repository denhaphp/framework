<?php
//------------------------
// 缓存类
//-------------------------
namespace denha;

class Cache
{
    public static $instance = [];

    public static function connect()
    {
        $type = config('cache')['type'] ? config('cache')['type'] : 'File';

        if (empty(self::$instance[$type])) {
            $class                 = 'denha\cache\\' . $type;
            self::$instance[$type] = $class::init();
        }

        return self::$instance[$type];
    }

}
