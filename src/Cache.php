<?php
//------------------------
//· 缓存类
//-------------------------
namespace denha;

use denha\Config;

class Cache
{
    public static $instance = [];
    public $id;

    public static function connect($options = [])
    {
        $config = !empty($options) ? $options : Config::get('cache');
        $type   = !empty($config['type']) ? $config['type'] : 'File';
        $id     = md5(json_encode($config));

        if (!isset(self::$instance[$id])) {
            $class = 'denha\cache\\' . $type;

            self::$instance[$id] = $class::init($config);
        }

        return self::$instance[$id];
    }

}
