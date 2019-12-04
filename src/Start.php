<?php
namespace denha;

use denha\Config;
use \ReflectionClass;
use \ReflectionMethod;

class Start
{
    public static $client;
    public static $config           = [];
    public static $httpResource     = [];
    public static $methodDocComment = ''; // 当前运行方法注解

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
        // 获取配置文档信息
        self::$config = array_merge(Config::includes(), (array) Config::includes('config.' . APP . '.php'));

        register_shutdown_function('denha\Trace::catchError');
        set_error_handler('denha\Trace::catchNotice');
        set_exception_handler('denha\Trace::catchApp');
        error_reporting(0);

        Start::checkDisk(); //检测磁盘容量

        Start::filter(); //过滤

        $class  = Route::main($route); //解析路由
        $action = lcfirst(parsename(ACTION, 1)); // 方法名称

        self::$httpResource = new HttpResource(); // 请求资源
        $request            = self::$httpResource->getRequest();

        $object = new ReflectionClass($class); // 获取类信息

        // 进入post提交方法
        if (($request['method'] == 'POST' || $request['method'] == 'AJAX') && $object->hasMethod($action . 'Post')) {
            $methodAction = $action . 'Post';
        } else {
            $methodAction = $action;
        }

        $method = new ReflectionMethod($class, $methodAction); // 直接获取方法信息

        // 只有公共方法可以调用
        if (!$method->isPublic()) {
            throw new Exception(Route::$class . ' NOT PUBLIC [ ' . $methodAction . ' ] ACTION');
        }

        self::$methodDocComment = $method->getDocComment(); // 保存方法注解信息

        $params = [];
        foreach ($method->getParameters() as $item) {
            if ($request['method'] == 'POST' && isset($request['params']['post'][$item->name])) {
                $params[$item->name] = $request['params']['post'][$item->name];
            } elseif ($request['method'] == 'GET' && isset($request['params']['get'][$item->name])) {
                $params[$item->name] = $request['params']['get'][$item->name];
            }
        }

        // 方法过滤 带post提交专用过滤方法
        // if ($object->hasMethod($methodAction . 'Validate')) {
        //     $methodFilter = new ReflectionMethod($class, $methodAction . 'Validate'); // 直接获取方法信息
        //     $params       = $methodFilter->invokeArgs(new $class(), [$params]) ?: $params;
        // }
        // // 方法过滤 默认通用普通过滤方法
        // elseif ($object->hasMethod($action . 'Validate')) {
        //     $methodFilter = new ReflectionMethod($class, $action . 'Validate'); // 直接获取方法信息
        //     $params       = $methodFilter->invokeArgs(new $class(), [$params]) ?: $params;
        // }

        $method->invokeArgs(new $class(), $params);

    }

    /**
     * 过滤GET POST参数
     * @date   2017-07-26T17:20:10+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public static function filter()
    {
        $urlArr  = ['xss' => '\=\+\/v(?:8|9|\+|\/)|\%0acontent\-(?:id|location|type|transfer\-encoding)'];
        $argsArr = ['xss' => '[\'\\\'\;\*\<\>].*\bon[a-zA-Z]{3,15}[\s\\r\\n\\v\\f]*\=|\b(?:expression)\(|\<script[\s\\\\\/]|\<\!\[cdata\[|\b(?:eval|alert|prompt|msgbox)\s*\(|url\((?:\#|data|javascript)', 'sql' => '[^\{\s]{1}(\s|\b)+(?:select\b|update\b|insert(?:(\/\*.*?\*\/)|(\s)|(\+))+into\b).+?(?:from\b|set\b)|[^\{\s]{1}(\s|\b)+(?:create|delete|drop|truncate|rename|desc)(?:(\/\*.*?\*\/)|(\s)|(\+))+(?:table\b|from\b|database\b)|into(?:(\/\*.*?\*\/)|\s|\+)+(?:dump|out)file\b|\bsleep\([\s]*[\d]+[\s]*\)|benchmark\(([^\,]*)\,([^\,]*)\)|(?:declare|set|select)\b.*@|union\b.*(?:select|all)\b|(?:select|update|insert|create|delete|drop|grant|truncate|rename|exec|desc|from|table|database|set|where)\b.*(charset|ascii|bin|char|uncompress|concat|concat_ws|conv|export_set|hex|instr|left|load_file|locate|mid|sub|substring|oct|reverse|right|unhex)\(|(?:master\.\.sysdatabases|msysaccessobjects|msysqueries|sysmodules|mysql\.db|sys\.database_name|information_schema\.|sysobjects|sp_makewebtask|xp_cmdshell|sp_oamethod|sp_addextendedproc|sp_oacreate|xp_regread|sys\.dbms_export_extension)', 'other' => '\.\.[\\\\\/].*\%00([^0-9a-fA-F]|$)|%00[\'\\\'\.]'];

        $httpReferer = empty($_SERVER['HTTP_REFERER']) ? [] : [$_SERVER['HTTP_REFERER']];
        $queryString = empty($_SERVER['QUERY_STRING']) ? [] : [$_SERVER['QUERY_STRING']];

        GSF($queryString, $urlArr);
        GSF($httpReferer, $argsArr);
        GSF($_GET, $argsArr);
        GSF($_POST, $argsArr);
        GSF($_COOKIE, $argsArr);

    }

    //检测磁盘容量
    private static function checkDisk()
    {
        if (self::$config['check_disk']) {

            $free = number_format(DISK_TOTAL_SPACE / 1024 / 1024 / 1024, 2);
            if (self::$config['disk_space'] >= $free) {
                $title = URL . ' 磁盘容量不足' . self::$config['disk_space'] . 'G ip:' . getIP() . ' ' . $_SERVER['SERVER_PROTOCOL'];
                dao('Mail')->send(self::$config['send_mail'], $title, $title, ['save_log' => false]);
            }
        }
    }

}
