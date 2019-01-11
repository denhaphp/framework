<?php
//------------------------
//· 缓存类
//-------------------------
namespace denha;

class Cache
{
    public static $instance = [];
    public $id;

    public static function connect($options = [])
    {
        $config = !empty($options) ? $options : config('cache');
        $type   = !empty($config['type']) ? $config['type'] : 'File';
        $id     = md5(json_encode($config));

        if (!isset(self::$instance[$id])) {
            $class = 'denha\cache\\' . $type;

            self::$instance[$id] = $class::init($config);
        }

        return self::$instance[$id];
    }

}
