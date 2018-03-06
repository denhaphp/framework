<?php
namespace denha;

class Database
{

    //  数据库连接实例
    protected static $instance = array();

    public static function getInstance($config = '')
    {
        if (!$config) {
            if (getConfig('db.' . APP_CONFIG)) {
                $config = getConfig('db.' . APP_CONFIG);
            } else {
                $config = getConfig('db');
            }
        }

        $name = md5($config);

        if (!isset(self::$instance[$name])) {
            $class = '\denha\database\\' . ucwords($config['db_type']);

            self::$instance[$name] = $db = new $class($config);

        }

        return self::$instance[$name];

    }
}
