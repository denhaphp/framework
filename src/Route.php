<?php
//------------------------
//· 路由类
//---------------------

declare (strict_types = 1);

namespace denha;

use denha\Config;

class Route
{
    public static $path;
    public static $class;
    public static $uri;
    public static $rule   = [];
    public static $config = []; // 配置信息
    // 当前路由信息
    public static $thisRule = [
        'uri'  => '', // 原生地址
        'rule' => [], // 改写路由信息
    ];
    public static $id         = 0;
    public static $regularUrl = []; // 路由规则数组

    // 执行主体
    public static function main()
    {
        self::$config          = Config::get('route');
        self::$thisRule['uri'] = self::$uri = self::parseUri();

        // 检查规则路由
        if (self::$config['open_route']) {
            // 加载路由规则文件
            $routeFiles = (array) self::$config['route_files'];
            foreach ($routeFiles as $file) {
                include_once $file;
            }

            // 获取当前url
            self::$uri = self::getRouteUrl('/' . self::$uri);
        }

        // 转换Url参数为GET参数
        $uriArr = explode('/s/', self::$uri);
        if (isset($uriArr[1])) {
            self::changeGetValue($uriArr[1], ['isGet' => true]);
        }

        $routeArr = explode('/', ltrim(reset($uriArr), '/'));

        // 开启指定结构层数
        if (self::$config['open_level']) {
            $routeArr = array_values(array_slice($routeArr, 0, self::$config['level']));
        }

        define('MODULE', implode('.', array_slice($routeArr, 0, -2)));
        define('CONTROLLER', ucfirst(preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, implode(array_slice($routeArr, -2, 1)))));

        define('ACTION', end($routeArr));

        $class = ['app', str_replace('.', '\\', MODULE), CONTROLLER];

        return self::$class = implode('\\', $class);

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
     *                                             jump 自动跳转
     * @return [type]                              [description]
     */
    public static function rule(string $url, $changeUrl = null, array $options = [])
    {

        if (!$changeUrl) {
            return false;
        }

        $params      = isset($options['params']) ? $options['params'] : '';
        $suffix      = isset($options['suffix']) ? $options['suffix'] : '/';
        $limitSuffix = isset($options['limit_suffix']) ? explode(',', $options['limit_suffix']) : '';
        $oldUriHide  = isset($options['old_uri_hide']) ? $options['old_uri_hide'] : Config::get('route')['old_uri_hide'];
        $jump        = isset($options['jump']) ? $options['jump'] : false;

        self::$rule[self::$id] = [
            'url'          => $url,
            'change_url'   => $changeUrl,
            'params'       => $params,
            'suffix'       => $suffix,
            'limit_suffix' => $limitSuffix,
            'old_uri_hide' => $oldUriHide,
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
            self::$regularUrl['changeUrl'][md5($changeUrl . $params)] = self::$id;
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
        $uriArr = explode('/s/', $uri);

        if (isset($uriArr[1])) {
            $params    = $uriArr[1];
            $changeUrl = $uriArr[0];
        } else {
            $params    = '';
            $changeUrl = $uri;
        }

        $changeUrl = '/' . ltrim(trim($changeUrl), '/');

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
        if ($suffix) {
            // 删除后缀
            $changeUrl = str_replace('.' . $suffix, '', $changeUrl);
        }

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
        if (isset(self::$regularUrl['closure'][$cpmd5]) || isset(self::$regularUrl['closure'][$cmd5])) {
            $funs = self::$rule[self::$regularUrl['closure'][$cpmd5]]['change_url'] ?? self::$rule[self::$regularUrl['closure'][$cmd5]]['change_url'];

            if (is_callable($funs)) {
                die(call_user_func($funs));
            }
        }

        // 匹配changeUrl
        if (isset(self::$regularUrl['changeUrl'][$cpmd5]) || isset(self::$regularUrl['closure'][$cmd5])) {
            self::$thisRule['rule'] = self::$rule[self::$regularUrl['changeUrl'][$cpmd5]] ?? self::$rule[self::$regularUrl['changeUrl'][$cmd5]];
            self::changeGetValue(self::$thisRule['rule']['params']); // 保存GET参数

            $url = self::$thisRule['rule']['url'] . ($params ? '/s/' . $params : '');

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

        if (isset(self::$regularUrl['url'][$cpmd5]) || isset(self::$regularUrl['url'][$cmd5])) {
            self::$thisRule['rule'] = self::$rule[self::$regularUrl['url'][$cpmd5]] ?? self::$rule[self::$regularUrl['url'][$cmd5]];
            self::changeGetValue(self::$thisRule['rule']['params']); // 保存GET信息
            // 过滤多余的“/” 存在参数则传参数 存在后缀则添加后缀
            $url = '/' . ltrim((self::$thisRule['rule']['change_url'] . ($params ? '/s/' . $params : '') . self::$thisRule['rule']['suffix']), '/');
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

    // 解析路由
    private static function parseUri()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
        } elseif (isset($_SERVER['argv'][1])) {
            $uri = $_SERVER['argv'][1];
        }

        if (!empty($_SERVER['SCRIPT_NAME'])) {
            if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
                $uri = substr($uri, strlen($_SERVER['SCRIPT_NAME']));
            }
        }

        // 删除"/"
        $uri = trim($uri, '/');
        if (!$uri) {
            return false;
        }

        // 删除参数
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }

        if ($uri) {
            return $uri;
        } else {
            return false;
        }
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
