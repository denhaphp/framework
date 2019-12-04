<?php
date_default_timezone_set('PRC');

define('START_TIME', microtime(true)); // 程序开始时间
define('DS', DIRECTORY_SEPARATOR);

define('ROOT_PATH', dirname(dirname(dirname(__DIR__))) . DS); // 根目录
define('VENDOR_PATH', ROOT_PATH . 'vendor' . DS); // Composer插件目录
define('FARM_PATH', VENDOR_PATH . 'denha' . DS . 'framework' . DS . 'src' . DS); //框架根目录
define('APP_PATH', ROOT_PATH . 'appliaction' . DS); // 程序根目录
define('CONFIG_PATH', ROOT_PATH . 'conf' . DS); // 配置文档目录
define('CERT_PATH', ROOT_PATH . 'cert' . DS); // 证书文档目录
define('DATA_PATH', ROOT_PATH . 'data' . DS); // 缓存目录
define('DATA_CACHE_PATH', DATA_PATH . 'cache' . DS); // 缓存文件目录
define('DATA_TPL_PATH', DATA_PATH . 'tpl' . DS); // 缓存模板目录
define('DATA_SQL_PATH', DATA_PATH . 'sqlLog' . DS); // 数据库日志记录目录
define('DATA_RUN_PATH', DATA_PATH . 'runLog' . DS); // 程序运行日志记录目录
define('VIEW_PATH', ROOT_PATH . 'resources' . DS); // 资源目录
define('PUBLIC_PATH', ROOT_PATH . 'public' . DS); // 公共地址目录
define('EXT', '.php'); //文件后缀

define('HTTP_TYPE', ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://'); // HTTP OR HTTPS
define('URL', isset($_SERVER['HTTP_HOST']) ? HTTP_TYPE . $_SERVER['HTTP_HOST'] : ''); // 当前Url

define('IS_CGI', (0 === strpos(PHP_SAPI, 'cgi')) || (false !== strpos(PHP_SAPI, 'fcgi')) ? true : false);
define('IS_WIN', stristr(PHP_OS, 'WIN') ? true : false);
define('IS_CLI', PHP_SAPI == 'cli' ? true : false);

if (!IS_CLI) {
    define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);
    define('IS_GET', REQUEST_METHOD == 'GET' ? true : false);
    define('IS_POST', REQUEST_METHOD == 'POST' ? true : false);
    define('IS_PUT', REQUEST_METHOD == 'PUT' ? true : false);
    define('IS_DELETE', REQUEST_METHOD == 'DELETE' ? true : false);
    define('IS_AJAX', (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) || !empty($_POST['ajax']) || !empty($_GET['ajax']) ? true : false);
} else {
    define('IS_GET', false);
    define('IS_POST', false);
    define('IS_PUT', false);
    define('IS_DELETE', false);
    define('IS_AJAX', false);
}

define('TIME', $_SERVER['REQUEST_TIME']); //系统时间
define('START_USE_MENUS', memory_get_usage()); // 内存使用初始情况
define('DISK_TOTAL_SPACE', disk_total_space(ROOT_PATH)); // 程序所在硬盘总容量
define('DISK_FREE_SPACE', disk_free_space(ROOT_PATH)); // 程序所在硬盘使用容量

require_once VENDOR_PATH . 'autoload.php';
