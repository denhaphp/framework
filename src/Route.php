<?php
//------------------------
//· 路由类
//---------------------
namespace denha;

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
    public static $regularUrl = [];

    //执行主体
    public static function main()
    {
        self::$config          = config('route');
        self::$thisRule['uri'] = self::$uri = self::parseUri();

        // 检查规则路由
        if (self::$config['open_route']) {

            include_once CONFIG_PATH . 'route.php';

            self::$uri = self::getRouteUrl('/' . self::$uri);
        }

        //转换Url参数为GET参数
        $uriArr = explode('/s/', self::$uri);
        if (isset($uriArr[1])) {
            self::changeGetValue($uriArr[1], ['isGet' => true]);
        }

        $routeArr = explode('/', ltrim(reset($uriArr), '/'));

        // 开启指定结构层数
        if (self::$config['open_level']) {
            $routeArr = array_values(array_slice($baseUriArr, 0, self::$config['level']));
        }

        define('MODULE', implode('.', array_slice($routeArr, 0, -2)));
        define('CONTROLLER', parsename(implode(array_slice($routeArr, -2, 1)), true));
        define('ACTION', end($routeArr));

        $class = ['app', str_replace('.', '\\', MODULE), CONTROLLER];

        return self::$class = implode('\\', $class);

    }

    /**
     * 保存路由规则
     * @date   2018-07-13T09:32:01+0800
     * @author ChenMingjiang
     * @param  [type]                   $url       [description]
     * @param  string                   $changeUrl [description]
     * @param  array                    $options   [description]
     * @return [type]                              [description]
     */
    public static function rule($url, $changeUrl = '/', $options = [])
    {

        $params      = isset($options['params']) ? $options['params'] : '';
        $suffix      = isset($options['suffix']) ? $options['suffix'] : '/';
        $limitSuffix = isset($options['limit_suffix']) ? explode(',', $options['limit_suffix']) : '';
        $jump        = isset($options['jump']) ? $options['jump'] : false;

        $key = md5($changeUrl . $params);

        self::$rule[self::$id] = [
            'url'          => $url,
            'change_url'   => $changeUrl,
            'params'       => $params,
            'suffix'       => $suffix,
            'limit_suffix' => $limitSuffix,
            'jump'         => $jump,
        ];

        self::$regularUrl['url'][md5($url . $params)]             = self::$id;
        self::$regularUrl['changeUrl'][md5($changeUrl . $params)] = self::$id;

        self::$id++;
    }

    // 解析请求URL
    public static function parseRouteUri($uri)
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

    /** 获取实际路径 */
    public static function getRouteUrl($uri)
    {

        list($changeUrl, $params) = self::parseRouteUri($uri);

        // 获取后缀
        $suffix = pathinfo($changeUrl, PATHINFO_EXTENSION);
        if ($suffix) {
            // 删除后缀
            $changeUrl = str_replace('.' . $suffix, '', $changeUrl);
        }

        $isJump = false; //是否自动跳转

        // 原始地址+参数匹配 完全匹配
        $keyMD5 = md5($changeUrl . $params);
        if (isset(self::$regularUrl['changeUrl'][$keyMD5])) {
            $rule   = &self::$rule[self::$regularUrl['changeUrl'][$keyMD5]];
            $url    = $rule['url'];
            $isJump = $rule['jump'];

            self::$thisRule['rule'] = $rule; // 保存改写路由信息
            self::changeGetValue($rule['params']); // 保存GET参数
        }

        // 若完全匹配不存在 则不匹配参数只匹配路径  部分匹配
        if (!self::$thisRule['rule']) {
            $keyMD5 = md5($changeUrl);
            if (isset(self::$regularUrl['changeUrl'][$keyMD5])) {
                $rule   = &self::$rule[self::$regularUrl['changeUrl'][$keyMD5]];
                $url    = $rule['url'] . ($params ? '/s/' . $params : '');
                $isJump = $rule['jump'];

                self::$thisRule['rule'] = $rule; // 保存改写路由信息
                self::changeGetValue($rule['params']); // 保存GET参数

            }
        }

        //自动跳转Url
        if ($isJump) {
            die(header('Location:' . $url));
        }

        return isset($url) ? $url : $uri;
    }

    // 获取改写路径
    public static function getRouteChangeUrl($uri, $params = '')
    {

        list($changeUrl, $params) = self::parseRouteUri($uri);

        // 原始地址+参数匹配 完全匹配
        $keyMD5 = md5($changeUrl . $params);
        if (isset(self::$regularUrl['url'][$keyMD5])) {
            $rule = &self::$rule[self::$regularUrl['url'][$keyMD5]];
            self::changeGetValue($rule['params']); // 保存GET信息
            if ($rule['change_url']) {
                $url = $rule['change_url'] . $rule['suffix'];
            } else {
                $url = $rule['change_url'];
            }

        }

        // 若完全匹配不存在 则不匹配参数只匹配路径  部分匹配
        if (!isset($url)) {
            $keyMD5 = md5($changeUrl);
            if (isset(self::$regularUrl['url'][$keyMD5])) {
                $rule = &self::$rule[self::$regularUrl['url'][$keyMD5]];
                // 增加自带参数 过来 多"/"情况
                $url = '/' . ltrim(($rule['change_url'] . ($params ? '/s/' . $params : '') . $rule['suffix']), '/');

            }
        }

        return isset($url) ? $url : $uri;
    }

    // 获取当前路由信息
    public static function getRule()
    {
        return self::$thisRule;
    }

    //获取直接参数
    private static function initValue($flag, $value)
    {
        $res = (isset($_GET[$flag]) && $_GET[$flag] ? strip_tags($_GET[$flag]) : $value);
        return $res;
    }

    //解析路由
    private static function parseUri()
    {
        //去除urldecode转码 转码会导致get参数 带/解析错误
        // $uri = urldecode($_SERVER['REQUEST_URI']);

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
    public static function changeGetValue($uri, $options = [])
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
