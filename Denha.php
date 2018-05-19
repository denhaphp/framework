<?php
$GLOBALS['_beginTime'] = microtime(true); //程序开始时间

date_default_timezone_set('PRC');

define('MEMORY_LIMIT_ON', function_exists('memory_get_usage')); //判断是否存在内存消耗函数
define('DS', DIRECTORY_SEPARATOR);

define('ROOT_PATH', dirname(__DIR__) . DS); //根目录
define('APP_PATH', ROOT_PATH . 'appliaction' . DS); //程序根目录
define('FARM_PATH', ROOT_PATH . 'denha' . DS . 'framework' . DS); //框架根目录
define('CONFIG_PATH', ROOT_PATH . 'conf' . DS); //配置文档目录
define('DATA_PATH', ROOT_PATH . 'data' . DS); //缓存目录
define('VIEW_PATH', ROOT_PATH . 'resources' . DS); //资源目录
define('PUBLIC_PATH', ROOT_PATH . 'public' . DS); //公共地址目录
define('EXT', '.php'); //文件后缀

define('URL', isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : ''); //当前Url
define('TIME', $_SERVER['REQUEST_TIME']); //系统时间
define('IS_CGI', (0 === strpos(PHP_SAPI, 'cgi')) || (false !== strpos(PHP_SAPI, 'fcgi')) ? true : false);
define('IS_WIN', stristr(PHP_OS, 'WIN') ? true : false);
define('IS_CLI', PHP_SAPI == 'cli' ? true : false);

if (MEMORY_LIMIT_ON) {
    $GLOBALS['_startUseMems'] = memory_get_usage();
}

if (version_compare(PHP_VERSION, '5.4.0', '<')) {
    ini_set('magic_quotes_runtime', 0);
    define('MAGIC_QUOTES_GPC', get_magic_quotes_gpc() ? true : false);
} else {
    define('MAGIC_QUOTES_GPC', false);
}

if (!IS_CLI) {
    define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD']);
    define('IS_GET', REQUEST_METHOD == 'GET' ? true : false);
    define('IS_POST', REQUEST_METHOD == 'POST' ? true : false);
    define('IS_PUT', REQUEST_METHOD == 'PUT' ? true : false);
    define('IS_DELETE', REQUEST_METHOD == 'DELETE' ? true : false);
    define('IS_AJAX', (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')) || !empty($_POST['ajax']) || !empty($_GET['ajax']) ? true : false);
}

require FARM_PATH . 'AutoLoad.php';
require FARM_PATH . 'Function.php';

denha\AutoLoad::register();
