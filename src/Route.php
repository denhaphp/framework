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

        if (!config('open_uri_level')) {
            $uriArr = explode('/s/', self::$uri);
            //转换路由
            $routeArr = array_values(array_filter(explode('/', reset($uriArr))));

            define('MODULE', implode(DS, array_slice($routeArr, 0, -2)));
            define('CONTROLLER', parsename(implode(array_slice($routeArr, -2, 1)), true));
            define('ACTION', end($routeArr));

            $class = array('app', implode('\\', array_slice($routeArr, 0, -2)), CONTROLLER);

            self::changeGetValue($uriArr[1]);
        } else {
            $baseUriArr = explode('/', self::$uri);
            $routeArr   = array_slice($baseUriArr, 0, config('base_uri_level'));
            define('MODULE', implode('/', array_slice($routeArr, 0, -2)));
            define('CONTROLLER', parsename(implode(array_slice($routeArr, -2, 1)), true));
            define('ACTION', end($routeArr));

            if (strpos(self::$uri, '/s/') === false) {
                $uriArr[] = MODULE . '/' . CONTROLLER . '/' . ACTION;
                $uriArr[] = str_replace(MODULE, '', self::$uri);
            } else {
                $uriArr = explode('/s/', self::$uri);
            }

            $class = array('app', implode('\\', array_slice($routeArr, 0, -2)), CONTROLLER);

            self::changeGetValue($uriArr);

        }

        return self::$class = implode('\\', $class);

    }

    //app 路由结构
    //v1/user/index/index/2/ be appliaction/app/controller/v1/user/Index_2.php 中 index
    public static function app()
    {

        $uri = self::parseUri();

        $array = explode('/s/', $uri);
        //转换路由
        $pathArray = array_values(array_filter(explode('/', $array[0])));

        if (count($pathArray) >= 3) {
            $version = $pathArray[0];
            define('MODULE', $pathArray[1]);
            define('CONTROLLER', $pathArray[2]);

            //index方法 默认
            if (is_numeric($pathArray[3])) {
                define('ACTION', 'index');
            } else {
                define('ACTION', $pathArray[3]);
            }

            if (is_null(self::$path)) {
                self::$path = stripos(APP, '\\') === false ? APP . '\\' . 'app' : substr(APP, 0, stripos(APP, '\\'));
            }

            self::$class = 'app\\' . self::$path . '\\' . 'controller\\' . $pathArray[0] . '\\' . parsename(MODULE, false) . '\\' . parsename(CONTROLLER, true);

            //切换小版本
            if (is_numeric($pathArray[3])) {
                $version = $pathArray[0] . '.' . $pathArray[3];
                self::$class .= '_' . $pathArray[3];
            } elseif (isset($pathArray[4]) && is_numeric($pathArray[4])) {
                $version = $pathArray[0] . '.' . $pathArray[4];
                self::$class .= '_' . $pathArray[4];
            }

            define('APP_MAIN_PATH', self::$path);
            define('APP_VERSION', $version);

            //转换参数
            self::changeGetValue($array);
        }

    }

    //smca 路由结构
    // /h5/pay/cashier/index be appliaction/h5/controller/pay/cashier.php 中 index
    public static function smca()
    {

        $uri   = self::parseUri();
        $array = explode('/s/', $uri);

        //转换路由
        $pathArray = array_values(array_filter(explode('/', $array[0])));

        if (count($pathArray) >= 3) {
            $_GET['module']     = $pathArray[1];
            $_GET['controller'] = $pathArray[2];

            //index方法 默认
            if (is_numeric($pathArray[3]) || !isset($pathArray[3])) {
                $_GET['action'] = 'index';
            } else {
                $_GET['action'] = $pathArray[3];
            }
        }

        $module     = self::initValue('module', 'index');
        $controller = self::initValue('controller', 'index');
        $action     = self::initValue('action', 'index');

        is_null(self::$path) ?: self::$path = stripos(APP, '\\') !== false ? substr(APP, 0, stripos(APP, '\\')) . '\\' . $pathArray[0] : APP . '\\' . $pathArray[0];

        define('MODULE', $module);
        define('CONTROLLER', $controller);
        define('ACTION', $action);
        define('APP_MAIN_PATH', APP_PATH . self::$path);

        //转换参数
        self::changeGetValue($array);

        self::$class = 'app\\' . self::$path . '\\' . 'controller\\' . parsename(MODULE, false) . '\\' . parsename(CONTROLLER, true);
    }

    //mca 路由结构
    // /pay/cashier/index be appliaction/controller/pay/cashier.php 中 index
    public static function mca()
    {
        if (!isset($_GET['module']) && isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['REQUEST_URI'])) {
            $uri   = self::parseUri();
            $array = explode('/s/', $uri);

            //转换路由
            $pathArray = array_values(array_filter(explode('/', $array[0])));

            if ($pathArray) {
                if (isset($pathArray[0]) && $pathArray[0]) {
                    $_GET['module'] = $pathArray[0];
                    if (isset($pathArray[1]) && $pathArray[1]) {
                        if (is_numeric($pathArray[1])) {
                            $_GET['controller'] = 'detail';
                            $_GET['action']     = 'index';
                            $_GET['id']         = $pathArray[1];
                        } else {
                            $_GET['controller'] = $pathArray[1];
                        }

                        if (isset($pathArray[2]) && $pathArray[2]) {
                            if (is_numeric($pathArray[2])) {
                                $_GET['action'] = 'detail';
                                $_GET['id']     = $pathArray[2];
                            } else {
                                $_GET['action'] = $pathArray[2];
                            }
                        }
                    }
                }
            }

            //转换参数
            self::changeGetValue($array);
        }

        $module     = self::initValue('module', 'index');
        $controller = self::initValue('controller', 'index');
        $action     = self::initValue('action', 'index');

        !is_null(self::$path) ?: self::$path = APP ? APP : '';

        define('MODULE', $module);
        define('CONTROLLER', $controller);
        define('ACTION', $action);
        define('APP_MAIN_PATH', APP_PATH . self::$path);

        self::$class = 'app\\' . self::$path . '\\' . 'controller\\' . parsename(MODULE) . '\\' . parsename(CONTROLLER, true);

    }

    public static function ca()
    {
        if (!isset($_GET['controller']) && isset($_SERVER['SCRIPT_NAME']) && isset($_SERVER['REQUEST_URI'])) {
            $uri = self::parseUri();

            if ($uri) {
                $array = explode('/', $uri);
                if (isset($array[0]) && $array[0]) {
                    $_GET['controller'] = $array[0];
                    if (isset($array[1]) && $array[1]) {
                        if (is_numeric($array[1])) {
                            $_GET['action'] = 'detail';
                            $_GET['id']     = $array[1];
                        } else {
                            $_GET['action'] = $array[1];
                        }

                        //静态化
                        $total = count($array);
                        if ($total >= 4) {
                            for ($i = 3; $i < $total;) {
                                $_GET[$array[$i]] = $array[$i + 1];
                                $i += 2;
                            }
                        }
                    }
                }
            }
        }

        $controller = self::initValue('controller', 'index');
        $action     = self::initValue('action', 'index');

        define('MODULE', '');
        define('CONTROLLER', $controller);
        define('ACTION', $action);
        define('APP_MAIN_PATH', APP_PATH . APP);

        self::$path  = APP . '\\';
        self::$class = 'app\\' . APP . '\\' . 'controller\\' . parsename(CONTROLLER, true);
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
    private static function changeGetValue($uri)
    {
        //转换参数
        if (isset($uri[1])) {
            $paramArray = array_values(explode('/', $uri[1]));

            $total = count($paramArray);

            for ($i = 0; $i < $total;) {
                if (isset($paramArray[$i + 1])) {
                    $_GET[$paramArray[$i]] = urldecode($paramArray[$i + 1]);
                }
                $i += 2;
            }
        }
    }

}
