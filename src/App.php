<?php
declare (strict_types = 1);

namespace denha;

use denha\Config;
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
    public  function start()
    {

        // 获取配置文档信息
        self::$config = Config::includes();


        Exception::setConfig(self::$config); // 加载错误面板

        Route::main(); //解析路由

        // 日志记录
        Log::setChannel('Denha', ['formatter' => ['output' => '%message%']])->debug('-------------------------------------------------------------------------');
        Log::debug('Method:' . HttpResource::getMethod() . ' URL:' . HttpResource::getUrl() . ' Crontroller:' . MODULE . DS . CONTROLLER . DS . ACTION);

        $this->makeRouteRun(); // 运行控制器

    }

    protected  function makeRouteRun()
    {

        // 日志记录
        $action = lcfirst(parsename(ACTION, 1)); // 方法名称

        $object = new ReflectionClass(Route::$class); // 获取类信息
        // 进入post提交方法
        if ((HttpResource::$request['method'] == 'POST') && $object->hasMethod($action . 'Post')) {
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

        $method->invokeArgs(new Route::$class(), $params);

        self::$methodDocComment = $method->getDocComment(); // 保存方法注解信息

    }

    /**
     * 加载应用文件和配置
     * @access protected
     * @return void
     */
    protected function load(): void
    {
        $appPath = $this->getAppPath();

        if (is_file($appPath . 'common.php')) {
            include_once $appPath . 'common.php';
        }

        include_once $this->thinkPath . 'helper.php';

        $configPath = $this->getConfigPath();

        $files = [];

        if (is_dir($configPath)) {
            $files = glob($configPath . '*' . $this->configExt);
        }

        foreach ($files as $file) {
            $this->config->load($file, pathinfo($file, PATHINFO_FILENAME));
        }

        if (is_file($appPath . 'event.php')) {
            $this->loadEvent(include $appPath . 'event.php');
        }

        if (is_file($appPath . 'service.php')) {
            $services = include $appPath . 'service.php';
            foreach ($services as $service) {
                $this->register($service);
            }
        }
    }

}
