<?php
namespace denha;

class Start
{
    public static $client;
    public static $config = array();

    /**
     * [start description]
     * @date   2017-07-14T16:12:51+0800
     * @author ChenMingjiang
     * @param  string                   $client [配置文件名称]
     * @param  string                   $route  [路由模式 mca smca ca]
     * @return [type]                           [description]
     */
    public static function up($route = 'mca')
    {

        self::$client = APP_CONFIG;
        //获取配置文档信息
        self::$config = include CONFIG_PATH . 'config.php';
        if (is_file(CONFIG_PATH . 'config.' . APP . '.php')) {
            self::$config = array_merge(include (CONFIG_PATH . 'config.php'), include (CONFIG_PATH . 'config.' . APP . '.php'));
        }

        error_reporting(0);
        register_shutdown_function('denha\Trace::catchError');
        set_error_handler('denha\Trace::catchNotice');
        set_exception_handler('denha\Trace::catchApp');

        Start::checkDisk(); //检测磁盘容量

        Start::filter(); //过滤
        $class = Route::main($route); //解析路由

        if (class_exists($class)) {
            $object = new $class();
        } else {
            $object = false;
        }

        if (!$object) {
            throw new Exception('NOT FIND CONTROLLER [ ' . CONTROLLER . ' ] FROM : ' . $class = Route::$class);
        }

        $action = lcfirst(parsename(ACTION, 1));

        //如果是POST提交 并且存在 function xxxPost方法 则自动调用该方法
        !(IS_POST && method_exists($object, $action . 'Post')) ?: $action .= 'Post';

        if (!method_exists($object, $action)) {
            throw new Exception('Class : ' . Route::$class . ' NOT FIND [ ' . $action . ' ] ACTION');
        }

        $action = $object->$action();

    }

    /**
     * 过滤GET POST参数
     * @date   2017-07-26T17:20:10+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public static function filter()
    {
        $urlArr  = array('xss' => '\=\+\/v(?:8|9|\+|\/)|\%0acontent\-(?:id|location|type|transfer\-encoding)');
        $argsArr = array('xss' => '[\'\\\'\;\*\<\>].*\bon[a-zA-Z]{3,15}[\s\\r\\n\\v\\f]*\=|\b(?:expression)\(|\<script[\s\\\\\/]|\<\!\[cdata\[|\b(?:eval|alert|prompt|msgbox)\s*\(|url\((?:\#|data|javascript)', 'sql' => '[^\{\s]{1}(\s|\b)+(?:select\b|update\b|insert(?:(\/\*.*?\*\/)|(\s)|(\+))+into\b).+?(?:from\b|set\b)|[^\{\s]{1}(\s|\b)+(?:create|delete|drop|truncate|rename|desc)(?:(\/\*.*?\*\/)|(\s)|(\+))+(?:table\b|from\b|database\b)|into(?:(\/\*.*?\*\/)|\s|\+)+(?:dump|out)file\b|\bsleep\([\s]*[\d]+[\s]*\)|benchmark\(([^\,]*)\,([^\,]*)\)|(?:declare|set|select)\b.*@|union\b.*(?:select|all)\b|(?:select|update|insert|create|delete|drop|grant|truncate|rename|exec|desc|from|table|database|set|where)\b.*(charset|ascii|bin|char|uncompress|concat|concat_ws|conv|export_set|hex|instr|left|load_file|locate|mid|sub|substring|oct|reverse|right|unhex)\(|(?:master\.\.sysdatabases|msysaccessobjects|msysqueries|sysmodules|mysql\.db|sys\.database_name|information_schema\.|sysobjects|sp_makewebtask|xp_cmdshell|sp_oamethod|sp_addextendedproc|sp_oacreate|xp_regread|sys\.dbms_export_extension)', 'other' => '\.\.[\\\\\/].*\%00([^0-9a-fA-F]|$)|%00[\'\\\'\.]');

        $httpReferer = empty($_SERVER['HTTP_REFERER']) ? array() : array($_SERVER['HTTP_REFERER']);
        $queryString = empty($_SERVER['QUERY_STRING']) ? array() : array($_SERVER['QUERY_STRING']);
        GSF($queryString, $urlArr);
        GSF($httpReferer, $argsArr);
        GSF($_GET, $argsArr);
        GSF($_POST, $argsArr);
        GSF($_COOKIE, $argsArr);

        if (MAGIC_QUOTES_GPC) {
            $_GET     = array_map('GSS', $_GET);
            $_POST    = array_map('GSS', $_POST);
            $_COOKIE  = array_map('GSS', $_COOKIE);
            $_REQUEST = array_map('GSS', $_REQUEST);
        }
    }

    //检测磁盘容量
    private static function checkDisk()
    {
        if (self::$config['check_disk']) {

            $free = number_format($GLOBALS['_diskFreeSpace'] / 1024 / 1024 / 1024, 2);
            if (self::$config['disk_space'] >= $free) {
                $title = $_SERVER['HTTP_HOST'] . ' 磁盘容量不足' . self::$config['disk_space'] . 'G ip:' . getIP() . ' ' . $_SERVER['SERVER_PROTOCOL'];
                dao('Mail')->send(config('send_mail'), $title, $title, array('save_log' => false));

            }
        }
    }

}
