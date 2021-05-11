<?php
//------------------------
//· 错误调试信息
//---------------------

declare (strict_types = 1);

namespace denha;

use denha\HttpResource;
use denha\Log;
use \Whoops\Handler\JsonResponseHandler;
use \Whoops\Handler\PlainTextHandler;
use \Whoops\Handler\PrettyPageHandler;
use \Whoops\Run as ErrorRun;

class Exception extends \Exception
{
    /**
     * 保存异常页面显示的额外Debug数据
     * @var array
     */
    protected $data = [];

    private static $whoops;

    public static function hide(HttpResource $httpResource, $config)
    {

        self::$whoops = new ErrorRun;

        if ($httpResource->getMethod() == 'CLI') {
            self::$whoops->prependHandler(function () {
                echo 'denha have a error so kill';
            });
        } elseif ($httpResource->isAjax()) {
            self::$whoops->prependHandler(function () {

                header("http/1.1 404 not found");
                header("status: 404 not found");

                echo '{"errord":"denha have a error so kill"}';
            });
        } else {
            if (isset($config['error']['tpl']) && is_file($config['error']['tpl'])) {
                self::$whoops->prependHandler(function () use ($config) {

                    header("http/1.1 404 not found");
                    header("status: 404 not found");

                    return include $config['error']['tpl'];
                });
            }
        }

        // 保存错误日志
        if (isset($config['error']['save_log']) && $config['error']['save_log']) {
            self::$whoops->pushHandler(function ($exception, $inspector, $run) {
                if($exception->getMessage() != 'Class app\\Favicon.ico does not exist'){
                    Log::warning($exception->getMessage() . ' From: ' . $exception->getFile() . ' On Line: ' . $exception->getLine(), [
                        'trace' => $exception->getTrace(),
                        'url'   => HttpResource::getUri(),
                    ]);
                }
            });
        }

        self::$whoops->register();
    }

    /**
     * [run description]
     * @date   2019-12-23T09:45:04+0800
     * @author ChenMingjiang
     * @param  [type]                   $HttpResource [description]
     * @param  array                    $options      [description]
     * @return [type]                   [description]
     */
    public static function run(HttpResource $httpResource)
    {
        self::$whoops = new ErrorRun;

        // cli模式
        if ($httpResource->getMethod() == 'CLI') {
            self::$whoops->prependHandler(new PlainTextHandler);
        }
        //  ajax模式
        elseif ($httpResource->isAjax()) {
            self::$whoops->prependHandler(new JsonResponseHandler);
        }
        // web页面模式
        else {
            $handler = new PrettyPageHandler;
            $handler->setPageTitle("denha have errors");

         
            // $handler->setEditor(function ($file, $line) {error_log($file . $line, 3, DATA_RUN_PATH . '1.log');});
            self::$whoops->prependHandler($handler);
        }

        // 保存错误日志
        self::$whoops->pushHandler(function ($exception, $inspector, $run) {

            if($exception->getMessage() != 'Class app\\Favicon.ico does not exist'){
                Log::warning($exception->getMessage() . ' From: ' . $exception->getFile() . ' On Line: ' . $exception->getLine(), $exception->getTrace());
            }            
        });

        self::$whoops->register();

    }

    public function abort(\Closure $closure, $code = 200)
    {

        self::$whoops = new ErrorRun;

        self::$whoops->prependHandler(function () use ($code, $closure) {
            header("http/1.1 $code");
            $closure();
        });

        self::$whoops->register();

    }

}
