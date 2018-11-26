<?php
namespace denha;

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
     * @return [type]                                   [description]
     */
    protected function show($viewPath = '', $viewParamData = [], $options = [])
    {

        // true 自定义路径
        $peg = isset($options['peg']) ? $options['peg'] : false;
        // 单个视图关闭调试模式
        $trace = isset($options['trace']) ? $options['trace'] : true;

        if (get('all')) {
            extract(get('all'), EXTR_OVERWRITE);
        }

        if (self::$assign) {
            // 模板阵列变量分解成为独立变量
            extract(self::$assign, EXTR_OVERWRITE);
        }

        if ($viewParamData) {
            // 模板阵列变量分解成为独立变量
            extract($viewParamData, EXTR_OVERWRITE);
        }

        if (!$peg) {
            if (!$viewPath) {
                $path = VIEW_PATH . str_replace('.', DS, MODULE) . DS . parseName(CONTROLLER, false) . DS . ACTION . '.html';
            }
            //绝对路径
            elseif (stripos($viewPath, '/') === 0) {
                $path = VIEW_PATH . substr($viewPath, 1) . '.html';
            }
            //相对路径
            else {
                $path = VIEW_PATH . str_replace('.', DS, MODULE) . DS . $viewPath . '.html';
            }
        } else {
            $path = $viewPath;
        }

        if (!is_file($path)) {
            throw new Exception('视图地址:' . $path . '不存在 ');
        }

        $cachePath = DATA_TPL_PATH . md5($path) . '.php';

        ob_start();
        // 开启页面缓存
        if (is_file($cachePath) && filemtime($path) == filemtime($cachePath) && !config('debug')) {
            include $cachePath;
        } else {
            // 处理视图模板
            $template = new Template($path);
            $template->getContent();
            include $template->loadPath;
        }

        $content = ob_get_clean();

        // 标签翻译功能
        if (config('tag_trans')) {
            $content = $this->tagTrans($content);
        }

        echo $content;

        // 模块debug功能
        if (config('trace') && $trace) {
            Trace::run();
        }
    }

    /** 标签翻译功能 */
    protected function tagTrans($content)
    {

        $regular = '/{FY:(.*?):(.*?)}/is';
        preg_match_all('/{FY:(.*?):(.*?)}/is', $content, $matches);

        if (array_filter($matches)) {

            //组合翻译结构
            foreach ($matches[0] as $key => $value) {
                $transArray[$matches[2][$key]][] = $matches[1][$key];
            }

            //批量翻译
            if ($transArray) {
                foreach ($transArray as $key => $value) {
                    if ($key != 'zh') {
                        $transValue[$key] = dao('BaiduTrans')->baiduTrans($value, $key, 'zh');
                    } else {
                        foreach ($value as $key => $value) {
                            $transValue['zh'][$value] = $value;
                        }
                    }
                }

                foreach ($transValue as $key => $value) {
                    foreach ($value as $k => $v) {
                        $content = str_replace("{FY:$k:$key}", $v, $content);
                    }
                }
            }

        }

        return $content;
    }

    /**
     * ajax返回
     * @date   2017-06-13T22:48:29+0800
     * @author ChenMingjiang
     * @param  [type]                   $value [description]
     * @return [type]                          [description]
     */
    protected function ajaxReturn($value, $lg = 'zh')
    {
        header("Content-Type:application/json; charset=utf-8");
        $array = array(
            'status' => true,
            'data'   => array(),
            'msg'    => '操作成功',
        );
        $value = array_merge($array, $value);
        if ($lg != 'zh') {
            $value['msg'] = dao('BaiduTrans')->baiduTrans($value['msg'], $this->lg);
        }
        exit(json_encode($value));
    }

    /**
     * app返回参数
     * @date   2018-01-30T16:43:24+0800
     * @author ChenMingjiang
     * @param  [type]                   $value [description]
     * @param  [type]                   $lg    [国家]
     * @return [type]                          [description]
     */
    protected function appReturn($value, $lg = 'zh')
    {
        header("Content-Type:application/json; charset=utf-8");
        $array = array(
            'code'   => 200,
            'status' => true,
            'data'   => array(),
            'msg'    => '获取数据成功',
        );

        //控制开关
        if (Start::$config['app_debug']) {
            $debug = array(
                'debug' => array(
                    'param' => array(
                        'post'  => (array) post('all'),
                        'get'   => (array) get('all'),
                        'files' => $_FILES,
                    ),
                    'ip'    => getIP(),
                ),
            );
            $array = array_merge($array, $debug);
        }

        $value = array_merge($array, $value);
        if ($lg != 'zh') {
            $value['msg'] = dao('BaiduTrans')->baiduTrans($value['msg'], $this->lg);
        }

        exit(json_encode($value));
    }

    /**
     * jsonpReturn返回
     * @date   2017-08-07T10:41:59+0800
     * @author ChenMingjiang
     * @param  array                    $value    [description]
     * @param  string                   $callback [description]
     * @return [type]                             [description]
     */
    protected function jsonpReturn(array $value, $callback = '')
    {
        if ($callback) {
            exit($callback . '(' . json_encode($value) . ')');
        } else {
            $this->ajaxReturn($value);
        }

    }
}
