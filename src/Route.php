<?php
namespace denha;

class Route
{
    public static $path;
    public static $class;
    public static $uri;
    public static $rule   = [];
    public static $config = []; // 配置信息

    //执行主体
    public static function main($route = 'mca')
    {
        self::$config = config('route');
        self::$uri    = self::parseUri();
        // 检查规则路由
        if (self::$config['open_route']) {

            include_once CONFIG_PATH . 'route.php';

            self::$uri = self::getRouteUrl('/' . self::$uri);
        }

        //转换Url参数为GET参数
        $uriArr = explode('/s/', self::$uri);
        if (isset($uriArr[1])) {
            self::changeGetValue($uriArr[1]);
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
        self::$rule[] = [
            'url'        => $url,
            'change_url' => $changeUrl,
            'params'     => isset($options['params']) ? $options['params'] : '',
            'jump'       => isset($options['jump']) ? $options['jump'] : false,
        ];
    }

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

        $isJump = false; //是否自动跳转

        foreach (self::$rule as $rule) {
            if ($rule['change_url'] == $changeUrl && $rule['params'] && $rule['params'] == $params) {
                self::changeGetValue($rule['params']);
                $url    = $rule['url'];
                $isJump = $rule['jump'];
                break;
            } elseif ($rule['change_url'] == $changeUrl) {
                $url    = $rule['url'] . ($params ? '/s/' . $params : '');
                $isJump = $rule['jump'];
                break;
            }
        }

        //自动跳转Url
        if ($isJump) {
            die(header('Location:' . $url));
        }

        return isset($url) ? $url : $uri;
    }

    public static function getRouteChangeUrl($uri, $params = '')
    {

        list($changeUrl, $params) = self::parseRouteUri($uri);

        foreach (self::$rule as $rule) {
            if ($rule['url'] == $changeUrl && $rule['params'] && $rule['params'] == $params) {
                self::changeGetValue($rule['params']);
                $url = $rule['change_url'];
                break;
            } elseif ($rule['url'] == $changeUrl) {
                $url = $rule['change_url'] . ($params ? '/s/' . $params : '');
                break;
            }
        }

        return isset($url) ? $url : $uri;
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

                $result[$paramArray[$i]] = urldecode($paramArray[$i + 1]);
            }
            $i += 2;
        }

        return $result;

    }

}
