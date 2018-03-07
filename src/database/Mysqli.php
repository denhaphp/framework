<?php
namespace denha\database;

use denha\database\Builder;
use denha\Exception;

/**
 * mysql数据库驱动
 */
class Mysqli extends Builder
{
    public function __construct($config)
    {
        parent::__construct($config);
    }

    public static function parseDsn($config)
    {
        if ($config['dsn']) {
            return $config['dsn'];
        }

        if (!$config) {
            throw new Exception('数据库配置信息不存在');
        }

        if (!empty($config['socket'])) {
            $dsn = 'mysql:unix_socket=' . $config['socket'];
        } elseif (!empty($config['db_port'])) {
            $dsn = 'mysql:host=' . $config['db_host'] . ';port=' . $config['db_port'];
        } else {
            $dsn = 'mysql:host=' . $config['db_host'];
        }
        $dsn .= ';dbname=' . $config['db_name'];

        if (!empty($config['db_charset'])) {
            $dsn .= ';charset=' . $config['db_charset'];
        }

        return $dsn;
    }
}
