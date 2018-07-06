<?php
namespace denha;

class Route
{
    public static $path;
    public static $class;
    public static $uri;
    public static $rule;

    //执行主体
    public static function main($route = 'mca')
    {
        self::$uri = self::parseUri();
        // 检查规则路由
        self::$uri = dao('RouteRule')->getRouteUrl('/' . self::$uri);

        //转换Url参数为GET参数
        $uriArr = explode('/s/', self::$uri);
        if (isset($uriArr[1])) {
            self::changeGetValue($uriArr[1]);
        }

        $routeArr = explode('/', ltrim(reset($uriArr), '/'));

        // 开启指定结构层数
        if (config('open_uri_level')) {
            $routeArr = array_values(array_slice($baseUriArr, 0, config('base_uri_level')));
        }

        define('MODULE', implode('.', array_slice($routeArr, 0, -2)));
        define('CONTROLLER', parsename(implode(array_slice($routeArr, -2, 1)), true));
        define('ACTION', end($routeArr));

        $class = ['app', str_replace('.', '\\', MODULE), CONTROLLER];

        return self::$class = implode('\\', $class);

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
        $uri = $_SERVER['REQUEST_URI'];

        if (strpos($uri, $_SERVER['SCRIPT_NAME']) === 0) {
            $uri = substr($uri, strlen($_SERVER['SCRIPT_NAME']));
        }

        //拆分数组
        $uri = trim($uri, '/');

        if (!$uri) {
            return false;
        }

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
     * @date   2018-01-16T11:29:07+0800
     * @author ChenMingjiang
     * @param  [type]                   $array [description]
     * @return [type]                          [description]
     */
    public static function changeGetValue($uri)
    {
        //转换参数

        $paramArray = array_values(explode('/', $uri));

        $total = count($paramArray);

        for ($i = 0; $i < $total;) {
            if (isset($paramArray[$i + 1])) {
                $_GET[$paramArray[$i]] = urldecode($paramArray[$i + 1]);
            }
            $i += 2;
        }

    }

}
