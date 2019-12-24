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

    /**
     * [run description]
     * @date   2019-12-23T09:45:04+0800
     * @author ChenMingjiang
     * @param  [type]                   $HttpResource [description]
     * @param  array                    $options      [description]
     * @return [type]                   [description]
     */
    public static function run(HttpResource $httpResource, $config)
    {

        $whoops = new ErrorRun;

        // 隐藏错误信息
        if ($config['debug'] == false) {

            header("http/1.1 404 not found");
            header("status: 404 not found");

            if ($httpResource->getMethod() == 'CLI') {
                $whoops->prependHandler(function () {echo 'denha have a error so kill';});
            } elseif ($httpResource->isAjax()) {
                $whoops->prependHandler(function () {echo '{"errord":"denha have a error so kill"}';});
            } else {
                $whoops->prependHandler(function () use ($config) {return include $config['error']['tpl'];});
            }

            // 保存错误日志
            if ($config['error']['save_log']) {
                $whoops->pushHandler(function ($exception, $inspector, $run) {
                    Log::warning($exception);
                });
            }
        }
        // cli模式
        elseif ($httpResource->getMethod() == 'CLI') {
            $whoops->prependHandler(new PlainTextHandler);
        }
        //  ajax模式
        elseif ($httpResource->isAjax()) {
            $whoops->prependHandler(new JsonResponseHandler);
        }
        // web页面模式
        else {
            $handler = new PrettyPageHandler;
            $handler->setPageTitle("denha have errors");
            // $handler->setEditor(function ($file, $line) {error_log($file . $line, 3, DATA_RUN_PATH . '1.log');});
            $whoops->prependHandler($handler);
        }

        $whoops->register();

    }

    /**
     * 设置异常额外的Debug数据
     * 数据将会显示为下面的格式
     *
     * Exception Data
     * --------------------------------------------------
     * Label 1
     *   key1      value1
     *   key2      value2
     * Label 2
     *   key1      value1
     *   key2      value2
     *
     * @param string $label 数据分类，用于异常页面显示
     * @param array  $data  需要显示的数据，必须为关联数组
     */
    final protected function setData($label, array $data)
    {
        $this->data[$label] = $data;
    }

    /**
     * 获取异常额外Debug数据
     * 主要用于输出到异常页面便于调试
     * @return array 由setData设置的Debug数据
     */
    final public function getData()
    {
        return $this->data;
    }

}
