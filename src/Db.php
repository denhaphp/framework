<?php
declare (strict_types = 1);

namespace denha;

use denha\Config;

class Db
{
    private static $instance = [];
    private static $handler  = [
        'mysql'  => \denha\db\handler\Mysql::class,
        'sqlite' => \denha\db\handler\Sqlite::class,
    ];

    public static function connection($type = 'mysql', array $config = [])
    {
        $type = strtolower($type);

        if (isset(self::$handler[$type])) {
            $class = self::$handler[$type];
        }

        $config = $config ? $config : self::getConfs($type);

        $name = md5(serialize($config) . $type);
        if (!isset(self::$instance[$name])) {
            self::$instance[$name] = new $class($config);
        }

        return self::$instance[$name];
    }

    public static function getConfs($name)
    {
        $config = Config::includes(Config::get('db_file'))[$name];

        if (!$config) {
            throw new Exception("Not Find db Conf from :" . Config::get('db_file') . '[' . $name . ']');
        }

        return $config;

    }

    /**
     * 调用驱动类的方法
     * @access public
     * @param  string $method 方法名
     * @param  array  $params 参数
     * @return mixed
     */
    public static function __callStatic($method, $params)
    {
        return self::connection()->$method(...$params);
    }
}
