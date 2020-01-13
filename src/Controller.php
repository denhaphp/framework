<?php
//------------------------
//· 控制器类
//---------------------

declare (strict_types = 1);

namespace denha;

use denha\App;
use denha\Config;
use denha\HttpResource;
use denha\Template;
use denha\Trace;

class Controller
{
    public static $assign = [];

    /**
     * 赋值
     * @date   2017-05-14T21:30:23+0800
     * @author ChenMingjiang
     * @param  [type]                   $field [变量名]
     * @param  [type]                   $value [变量值]
     * @return [type]                          [description]
     */
    protected function assign($name, $value = '')
    {
        if (is_array($name)) {
            self::$assign = array_merge(self::$assign, $name);
        } else {
            self::$assign[$name] = $value;
        }
    }

    /**
     * [show description]
     * @date   2018-06-25T20:37:55+0800
     * @author ChenMingjiang
     * @param  string                   $viewPath       [视图地址]
     * @param  array                    $viewParamData  [渲染变量值]
     * @param  array                    $options        [预定义参数]
     *                                                  trace:单个视图关闭调试模式 【默认】true：开启 fasle：关闭
     * @return [type]                                   [description]
     */
    protected function view(string $viewPath = '', array $viewParamData = [], array $options = [])
    {
        // 单个视图关闭调试模式
        $options['trace'] = isset($options['trace']) ? $options['trace'] : true;

        $viewParamData = array_merge($viewParamData, self::$assign);

        if (HttpResource::$request['params']['get']) {
            $viewParamData = array_merge($viewParamData, HttpResource::$request['params']['get']);
        }

        return [$viewPath, $viewParamData, $options];
    }

    public static function fetch($viewPath = '', array $viewParamData = [], array $options = [])
    {

        // 单个视图关闭调试模式
        $trace = isset($options['trace']) ? $options['trace'] : true;

        echo Template::parseContent(['view' => $viewPath, 'data' => $viewParamData]);

        // 模块debug功能
        if (Config::get('trace') && $trace) {
            Trace::run();
        }

    }

    /**
     * ajax返回
     * @date   2019-01-17T21:33:34+0800
     * ajaxReturn(true,'xxx',['abc'=>111]) => ['status'=>true,'msg'=>'xxx',data=>['abc'=>111]]
     * ajaxReturn(false,'xxx') => ['status'=>false,'msg'=>'xxx']
     * ajaxReturn(['status'=>true,'msg'=>'xxx','data'=>['abc'=>111]]) => ['status'=>true,'msg'=>'xxx','data'=>['abc'=>111]]
     * ajaxReturn(false,['abc'=>111]) => ['status'=>false,'msg'=>'操作成功',data=>['abc'=>111]]
     * @author ChenMingjiang
     * @param  [type]                   $status [array则直接合并 bool则表示status]
     * @param  [type]                   $msg    [array则表示为data值 string则表示msg]
     * @param  [type]                   $data   [返回参数]
     * @param  string                   $lg     [语言类型]
     * @return [type]                           [description]
     */
    protected function ajaxReturn($status, $msg = null, $data = null): string
    {
        header("Content-Type:application/json; charset=utf-8");

        // 处理参数信息
        if (is_array($status)) {
            $value = $status;
        } else {
            $value['status'] = $status;
            if (is_array($msg)) {
                $value['data'] = $msg;
            } elseif ($msg !== null) {
                $value['msg'] = $msg;
            }

            if ($data !== null) {
                $value['data'] = $data;
            }
        }

        $array = [
            'status' => true,
            'data'   => [],
            'msg'    => '操作成功',
        ];

        // 控制开关
        if (Config::get('app_debug')) {
            $debug = [
                'debug' => [
                    'param'      => [
                        'post'  => (array) HttpResource::$request['params']['post'],
                        'get'   => (array) HttpResource::$request['params']['get'],
                        'files' => $_FILES,
                    ],
                    'docComment' => !App::$methodDocComment ?: explode(PHP_EOL, App::$methodDocComment),
                    'ip'         => Config::IP(),
                    'sql'        => Trace::$sqlInfo,
                ],
            ];
            $array = array_merge($array, $debug);
        }

        $value = array_merge($array, $value);

        // jsonpReturn返回
        $callback = get('callbak', 'text', '');
        if ($callback && IS_GET) {
            return $callback . '(' . json_encode($value) . ')';
        }

        // 正常ajax返回
        return json_encode($value);
    }
}
