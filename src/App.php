<?php
declare (strict_types = 1);

namespace denha;

use denha\Config;
use denha\Controller;
use denha\Exception;
use denha\HttpResource;
use denha\Log;
use denha\Route;
use \ReflectionClass;
use \ReflectionMethod;

class App
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
    public function start($configPath = '')
    {

        Exception::run(HttpResource::initInstance()); // 加载错误面板

        $appPath = $this->getFramePath();
        // 加载配置文件
        if (is_file($appPath . 'env.php')) {
            include_once $appPath . 'env.php';
        }

        // 获取配置文档信息
        if ($configPath) {
            self::$config = Config::includes(['config.php', $configPath]);
        } else {
            self::$config = Config::includes();
        }

        if (!self::$config['debug']) {
            Exception::hide(HttpResource::initInstance(), self::$config); // 隐藏错误提示
        }

        // 载入助手函数
        foreach (self::$config['help_paths'] as $item) {
            if (!is_file($item)) {
                throw new Exception('path: ' . $item . ' is not file from config->help_paths');
            }

            include_once $item;
        }

        Route::main(); // 解析路由

        $view = $this->makeRouteRun();

        // 视图渲染
        if (is_array($view)) {
            Controller::fetch(...$view);
        } else {
            echo $view;
        }

        $this->runLog(); // 日志记录
    }

    protected function getFramePath()
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR;
    }

    protected function runLog()
    {

        // Log::debug('Method:' . HttpResource::getMethod() . ' Uri:' . HttpResource::getUrl());
        // Log::debug('Crontroller:' . MODULE . DS . CONTROLLER . DS . ACTION);
        // Log::debug('Sql:', ['lists' => Trace::$sqlInfo]);

        Log::debug('系统信息-----------------------------------------------' . PHP_EOL, [
            'Uri'         => HttpResource::getUrl(),
            'Method'      => HttpResource::getMethod(),
            'Sql'         => Trace::$sqlInfo,
            'Ip'          => Config::IP(),
            'Crontroller' => MODULE . DS . CONTROLLER . DS . ACTION,
        ]);

        Log::call(); // 关闭日志
    }

    protected function makeRouteRun()
    {

        // 日志记录
        $action = lcfirst(parsename(ACTION, 1)); // 方法名称

        $object = new ReflectionClass(Route::$class); // 获取类信息

        $request = &HttpResource::$request;
        // 进入post提交方法
        if (($request['method'] == 'POST') && $object->hasMethod($action . 'Post')) {
            $methodAction = $action . 'Post';
        } else {
            $methodAction = $action;
        }

        $method = new ReflectionMethod(Route::$class, $methodAction); // 直接获取方法信息

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

        self::$methodDocComment = $method->getDocComment(); // 保存方法注解信息

        return $method->invokeArgs(new Route::$class(), $params);
    }
}
