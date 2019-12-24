<?php
date_default_timezone_set('PRC');

define('START_TIME', microtime(true)); // 程序开始时间
define('DS', DIRECTORY_SEPARATOR);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))) . DS); // 根目录
define('VENDOR_PATH', ROOT_PATH . 'vendor' . DS); // Composer插件目录
define('FARM_PATH', VENDOR_PATH . 'denha' . DS . 'framework' . DS . 'src' . DS); //框架根目录
define('APP_PATH', ROOT_PATH . 'app' . DS); // 程序根目录
define('CONFIG_PATH', ROOT_PATH . 'conf' . DS); // 配置文档目录
define('CERT_PATH', ROOT_PATH . 'cert' . DS); // 证书文档目录
define('DATA_PATH', ROOT_PATH . 'storage' . DS); // 缓存目录
define('DATA_CACHE_PATH', DATA_PATH . 'cache' . DS); // 缓存文件目录
define('DATA_TPL_PATH', DATA_PATH . 'tpl' . DS); // 缓存模板目录
define('DATA_SQL_PATH', DATA_PATH . 'sql' . DS); // 数据库日志记录目录
define('DATA_RUN_PATH', DATA_PATH . 'run' . DS); // 程序运行日志记录目录
define('VIEW_PATH', ROOT_PATH . 'resources' . DS); // 资源目录
define('PUBLIC_PATH', ROOT_PATH . 'public' . DS); // 公共地址目录
define('EXT', '.php'); //文件后缀

define('TIME', $_SERVER['REQUEST_TIME']); //系统时间
define('START_USE_MENUS', memory_get_usage()); // 内存使用初始情况

require_once VENDOR_PATH . 'autoload.php';
