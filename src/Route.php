<?php
//------------------------
//· 路由类
//---------------------

declare (strict_types = 1);

namespace denha;

use denha\Config;
use denha\HttpResource;

class Route
{
    public static $class; // 请求类
    public static $uri; // 请求路由地址
    public static $rule   = [];
    public static $config = []; // 配置信息
    // 当前路由信息
    public static $thisRule = [
        'uri'  => '', // 原生地址
        'rule' => [], // 改写路由信息
    ];
    public static $id         = 0;
    public static $regularUrl = []; // 路由规则匹配数组

    /** 获取配置信息 */
    public static function getConfig()
    {
        if (!self::$config) {
            self::$config = Config::get('route');
        }
    }

    public static function make($class = '')
    {
        self::getConfig();

        $uri = self::$thisRule['uri'] = self::$uri = self::parseUri($class);

        $params = '';

        if ($uri && strpos($uri, '/s/') !== false) {
            list($uri, $params) = explode('/s/', $uri);
        }

        self::changeGetValue($params, ['isGet' => true]); //  转换Url参数为GET参数

        if ($uri === '' || $uri === false) {
            throw new Exception('Not Find Url');
        }

        $route = explode('\\', str_replace('/', '\\', ltrim($uri, '/')));

        // 开启指定结构层数
        if (self::$config['open_level']) {
            $route = array_values(array_slice($route, 0, self::$config['level']));
        }

        HttpResource::setModule(implode('.', array_slice($route, 0, -2)));
        HttpResource::setController(ucfirst(preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, implode(array_slice($route, -2, 1)))));
        HttpResource::setAction(end($route));
        HttpResource::setClass(implode('\\', ['app', str_replace('.', '\\', HttpResource::getModuleName()), HttpResource::getControllerName()]));

        return HttpResource::getClass();

    }

    // 解析路由
    private static function parseUri($uri = null)
    {

        if (!$uri) {
            if (isset($_SERVER['REQUEST_URI'])) {
                $uri = $_SERVER['REQUEST_URI'];
            } elseif (isset($_SERVER['argv'][1])) {
                $uri = $_SERVER['argv'][1];
            }

            // 过滤SCRIPT_NAME
            if (!empty($_SERVER['SCRIPT_NAME']) && strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
                $uri = substr($uri, strlen($_SERVER['SCRIPT_NAME']));
            }
        }

        HttpResource::setUri($uri); // 资源记录Uri信息

        // 删除参数
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        // 检查规则路由
        if (self::$config['open_route']) {
            self::loadRouteFiles(); // 载入路由规则文件
            $uri = self::getRouteUrl($uri); // 获取当前url
        }

        if ($uri) {
            return $uri;
        } else {
            return false;
        }
    }

    /** 载入路由规则文件 */
    private static function loadRouteFiles()
    {
        // 加载路由规则文件
        $routeFiles = (array) self::$config['route_files'];

        foreach ($routeFiles as $file) {
            if (!is_file($file)) {
                throw new Exception('Not Find Rout File :' . $file);
            }

            include_once $file;
        }
    }

    /**
     * 保存路由规则
     * @date   2019-12-19T09:32:01+0800
     * @author ChenMingjiang
     * @param  [type]                   $url       [当前路由信息]
     * @param  string                   $changeUrl [改写路由信息]
     * @param  array                    $options   [description]
     *                                             params 参数隐藏
     *                                             suffix 指定后缀名
     *                                             limit_suffix 限制后缀名
     *                                             hide_url true:隐藏原生url false:不隐藏
     *                                             old_uri_hide true:禁止原路由地址访问 false:不禁止原路由访问
     *                                             hiden_field String/Array:隐藏参数名称多个“,”分割/一位数组指定 false:不隐藏参数名称 默认不隐藏
     *                                             jump 自动跳转
     * @return [type]                              [description]
     */
    public static function rule(string $url, $changeUrl = null, array $options = [])
    {

        if (!$changeUrl) {
            return false;
        }

        $params      = $options['params'] ?? '';
        $suffix      = $options['suffix'] ?? '/';
        $oldUriHide  = $options['old_uri_hide'] ?? Config::get('route')['old_uri_hide'];
        $hidenField  = $options['hiden_field'] ?? false;
        $jump        = $options['jump'] ?? false;
        $limitSuffix = isset($options['limit_suffix']) ? explode(',', $options['limit_suffix']) : '';

        self::$rule[self::$id] = [
            'url'          => $url,
            'change_url'   => $changeUrl,
            'params'       => $params,
            'suffix'       => $suffix,
            'limit_suffix' => $limitSuffix,
            'old_uri_hide' => $oldUriHide,
            'hiden_field'  => is_array($hidenField) ? $hidenField : ($hidenField ? (array) explode(',', $hidenField) : $hidenField),
            'jump'         => $jump,
        ];

        // 加入黑名单列表
        if ($oldUriHide) {
            self::$regularUrl['blacklist'][md5($url . $params)] = self::$id;
        }

        // 闭包访问组 闭包访问组不存在改写情况则单独分出来
        if (is_object($changeUrl) || $changeUrl instanceof \Closure) {
            self::$regularUrl['closure'][md5($url . $params)] = self::$id;
        }
        // 改写信息
        else {
            if (self::$rule[self::$id]['hiden_field']) {
                $fields = '';
                foreach (self::$rule[self::$id]['hiden_field'] as $field) {
                    $fields .= '\/([a-zA-Z0-9]*)';
                }
                self::$regularUrl['changeUrlAll'][self::$id] = '/^\\' . $changeUrl . $fields . '/i';
            } else {
                self::$regularUrl['changeUrl'][md5($changeUrl)]           = self::$id;
                self::$regularUrl['changeUrl'][md5($changeUrl . $params)] = self::$id;
            }
        }

        // 原生信息
        self::$regularUrl['url'][md5($url . $params)] = self::$id;

        self::$id++;

    }

    /**
     * 解析当前请求URL
     * @date   2019-12-19T13:54:08+0800
     * @author ChenMingjiang
     * @param  [type]                   $uri [description]
     * @return [type]                   [description]
     */
    public static function parseRouteUri(string $uri)
    {

        if (strpos($uri, '/s/') !== false) {
            list($changeUrl, $params) = explode('/s/', $uri);
        } else {
            $params    = '';
            $changeUrl = $uri;
        }

        $changeUrl = '/' . trim(trim($changeUrl), '/');

        return [$changeUrl, $params];
    }

    /**
     * 根据改写路径获取实际路径
     * @date   2019-12-19T13:53:53+0800
     * @author ChenMingjiang
     * @param  [type]                   $uri [改写url]
     * @return [type]                   [description]
     */
    public static function getRouteUrl(string $uri)
    {
        // 分解当前Url
        list($changeUrl, $params) = self::parseRouteUri($uri);

        // 获取后缀
        $suffix = pathinfo($changeUrl, PATHINFO_EXTENSION);
        // 删除后缀
        if ($suffix) {
            $changeUrl = str_replace('.' . $suffix, '', $changeUrl);
        }

        $params    = rtrim($params, '/');
        $changeUrl = '/' . ltrim($changeUrl, '/') ?: '/';

        $cpmd5 = md5($changeUrl . $params); // 参数+地址匹配
        $cmd5  = md5($changeUrl); // 纯地址匹配

        // 判断是否存在原生地址黑名单黑名单
        if (isset(self::$regularUrl['blacklist'][$cpmd5]) || isset(self::$regularUrl['blacklist'][$cmd5])) {
            if (Config::get('debug')) {
                throw new Exception('当前路由已被禁止访问');
            } else {
                throw new Exception('禁止访问');
            }
        }

        // 如果存在闭包信息则直接返回
        if (($isCpmd5 = isset(self::$regularUrl['closure'][$cpmd5])) || isset(self::$regularUrl['closure'][$cmd5])) {
            $funs = $isCpmd5 === true ? self::$rule[self::$regularUrl['closure'][$cpmd5]]['change_url'] : self::$rule[self::$regularUrl['closure'][$cmd5]]['change_url'];

            if (is_callable($funs)) {
                die(call_user_func($funs));
            }
        }

        // 匹配changeUrl
        if (($isCpmd5 = isset(self::$regularUrl['changeUrl'][$cpmd5])) || isset(self::$regularUrl['changeUrl'][$cmd5])) {
            self::$thisRule['rule'] = $isCpmd5 === true ? self::$rule[self::$regularUrl['changeUrl'][$cpmd5]] : self::$rule[self::$regularUrl['changeUrl'][$cmd5]];

            self::changeGetValue(self::$thisRule['rule']['params'], ['isGet' => true]); // 保存GET参数

            $url = self::$thisRule['rule']['url'] . ($params ? '/s/' . $params : '');

        }

        // 匹配隐藏参数名称规则
        if (isset(self::$regularUrl['changeUrlAll'])) {
            foreach (self::$regularUrl['changeUrlAll'] as $id => $rule) {
                if (preg_match($rule, $changeUrl, $matchs)) {
                    self::$thisRule['rule'] = self::$rule[$id];

                    foreach (self::$thisRule['rule']['hiden_field'] as $key => $field) {
                        self::$thisRule['rule']['params'] .= $field . '/' . (isset($matchs[$key + 1]) ? $matchs[$key + 1] : '');
                    }

                    self::changeGetValue(self::$thisRule['rule']['params'], ['isGet' => true]); // 保存GET参数

                    $url = self::$thisRule['rule']['url'] . ($params ? '/s/' . $params : '');

                    break;
                }
            }
        }

        return $url ?? $uri;
    }

    /**
     * 根据原始路径获取改写路径
     * @date   2019-12-19T13:54:22+0800
     * @author ChenMingjiang
     * @param  [type]                   $uri    [原始路径]
     * @param  string                   $params [参数]
     * @return [type]                   [description]
     */
    public static function getRouteChangeUrl(string $uri, $params = '')
    {

        list($changeUrl, $params) = self::parseRouteUri($uri);

        $cpmd5 = md5($changeUrl . $params); // 参数+地址匹配
        $cmd5  = md5($changeUrl); // 纯地址匹配

        if (($isCpmd5 = isset(self::$regularUrl['url'][$cpmd5])) || isset(self::$regularUrl['url'][$cmd5])) {
            self::$thisRule['rule'] = $isCpmd5 === true ? self::$rule[self::$regularUrl['url'][$cpmd5]] : self::$rule[self::$regularUrl['url'][$cmd5]];
            self::changeGetValue(self::$thisRule['rule']['params']); // 保存GET信息

            // 过滤多余的“/” 存在参数则传参数 存在后缀则添加后缀
            $urlParams = self::$thisRule['rule']['params'] === $params ? '' : $params;

            // 如果存在隐藏参数名称规则
            if (self::$thisRule['rule']['hiden_field'] && $urlParams) {

                $urlParams = explode('/', $urlParams);
                foreach ($urlParams as $key => $field) {
                    // 参数字段 存在隐藏
                    if ($key % 2 == 0 && in_array($field, self::$thisRule['rule']['hiden_field'])) {
                        unset($urlParams[$key]);
                    }
                }

                $urlParams = '/' . implode('/', $urlParams);

            } elseif ($urlParams) {
                $urlParams = '/s/' . $urlParams;
            }

            $url = '/' . ltrim((self::$thisRule['rule']['change_url'] . $urlParams . self::$thisRule['rule']['suffix']), '/');
        }

        return $url ?? $uri;
    }

    // 获取当前路由信息
    public static function getRule()
    {
        return self::$thisRule;
    }

    // 获取直接参数
    private static function initValue(string $flag, $value)
    {
        $res = (isset($_GET[$flag]) && $_GET[$flag] ? strip_tags($_GET[$flag]) : $value);
        return $res;
    }

    /**
     * 转换GET参数
     * GET原始模式的参数优先级最高
     * @date   2019-02-27T11:16:12+0800
     * @author ChenMingjiang
     * @param  [type]                   $uri     [description]
     * @param  array                    $options [description]
     *                                           isGet:是否保存GET值 默认不保存
     * @return [type]                   [description]
     */
    public static function changeGetValue(string $uri, array $options = [])
    {

        $isGet = isset($options['isGet']) ? isset($options['isGet']) : false;

        if (!$uri) {
            return false;
        }

        //转换参数

        $paramItems = array_values(explode('/', $uri));

        $total = count($paramItems);

        $result = [];

        for ($i = 0; $i < $total;) {
            if (isset($paramItems[$i + 1])) {
                // 匹配数组
                $regular = '/(.*?)\[(.*?)\]/';
                preg_match($regular, $paramItems[$i], $matches);

                if ($matches) {

                    $result[$matches[1]][$matches[2]] = urldecode($paramItems[$i + 1]);

                    if ($isGet) {
                        // 保存数组信息 如果不存在 $_GET 信息
                        if (!isset($_GET[$matches[1]][$matches[2]])) {
                            $_GET[$matches[1]][$matches[2]] = urldecode($paramItems[$i + 1]);
                        }

                        $result[$matches[1]][$matches[2]] = $_GET[$matches[1]][$matches[2]];
                    }

                } else {
                    $result[$paramItems[$i]] = urldecode($paramItems[$i + 1]);
                    if ($isGet) {
                        // 保存数组信息 如果不存在 $_GET 信息
                        if (!isset($_GET[$paramItems[$i]])) {
                            $_GET[$paramItems[$i]] = urldecode($paramItems[$i + 1]);
                        }

                        $result[$paramItems[$i]] = $_GET[$paramItems[$i]];
                    }

                }
            }
            $i += 2;
        }

        return $result;

    }

}
