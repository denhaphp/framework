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

    /**
     * 指定链接数据库
     * @date   2020-12-04T14:42:02+0800
     * @author ChenMingjiang
     * @param  string                   $handlerType [驱动类型]
     * @param  array                    $config      [description]
     * @return [type]                   [description]
     */
    public static function connection($handlerType = 'mysql', array $config = [])
    {
        $type = strtolower($handlerType);

        if (isset(self::$handler[$type])) {
            $class = self::$handler[$type];
        }

        $config = $config ? $config : self::getConfs($type);
        $name   = md5(serialize($config) . $type);

        if (!isset(self::$instance[$name])) {
            self::$instance[$name] = new $class($config);
        }

        return self::$instance[$name];
    }

    /**
     * 通过配置文件链接指定数据库
     * @date   2020-12-04T14:35:09+0800
     * @author ChenMingjiang
     * @param  string                   $file [description]
     * @return [type]                   [description]
     */
    public static function connectionPath(string $file)
    {
        return self::connection($type, $config);
    }

    public static function getConfs($name)
    {

        $config = Config::includes(Config::get('db_file'));

        if (!isset($config[$name])) {
            throw new Exception("Not Find db.Type Conf from :" . Config::get('db_file') . '[' . $name . ']');
        }

        if (!$config) {
            throw new Exception("Not Find db Conf from :" . Config::get('db_file') . '[' . $name . ']');
        }

        return $config[$name];

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
