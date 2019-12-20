<?php
namespace denha;

use denha\Config;
use denha\Exception;
use denha\HttpResource;
use \ReflectionClass;
use \ReflectionMethod;

class Start
{
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
    public static function up()
    {
        // 获取配置文档信息
        self::$config = array_merge(Config::includes(), (array) Config::includes('config.' . APP . '.php'));

        Exception::run(); // 加载错误面板

        HttpResource::initInstance(); // 请求资源
        
        self::makeRouteRun(); // 运行路由

        Start::checkDisk(); //检测磁盘容量
    }

    
    public static function makeRouteRun()
    {
        $class = Route::main(); //解析路由

        $action = lcfirst(parsename(ACTION, 1)); // 方法名称

        $object = new ReflectionClass($class); // 获取类信息
        // 进入post提交方法
        if ((HttpResource::$request['method'] == 'POST') && $object->hasMethod($action . 'Post')) {
            $methodAction = $action . 'Post';
        } else {
            $methodAction = $action;
        }

        $method = new ReflectionMethod($class, $methodAction); // 直接获取方法信息

        // 只有公共方法可以调用
        if (!$method->isPublic()) {
            throw new Exception(Route::$class . ' NOT PUBLIC [ ' . $methodAction . ' ] ACTION');
        }

        $params = [];
        foreach ($method->getParameters() as $item) {
            if ($request['method'] == 'POST' && isset($request['params']['post'][$item->name])) {
                $params[$item->name] = $request['params']['post'][$item->name];
            } elseif ($request['method'] == 'GET' && isset($request['params']['get'][$item->name])) {
                $params[$item->name] = $request['params']['get'][$item->name];
            }
        }

        $method->invokeArgs(new $class(), $params);

        self::$methodDocComment = $method->getDocComment(); // 保存方法注解信息

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
