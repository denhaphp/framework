<?php
//------------------------
//· 配置类
//-------------------------

declare (strict_types = 1);

namespace denha;

use denha\App;

class Config
{
    public static $includes = []; // 文件缓存
    public static $names    = []; // 具体到某个变量缓存

    /**
     * 获取配置基础信息
     * @date   2019-06-13T11:56:26+0800
     * @author ChenMingjiang
     * @param  string                   $name [description]
     * @param  string                   $path [默认空则获取系统配置]
     * @return [type]                   [description]
     */
    public static function get($name = null, $path = null)
    {

        $key = md5($name . $path);

        if (!isset(self::$names[$key])) {
            $names = explode('.', $name);
            $num   = count($names); // name个数

            $data = App::$config;

            // N维数组下最后一个数组值
            $fib = function ($thisNum = 0) use ($num, $names, &$data, &$fib) {
                if ($num > 0) {
                    $data = isset($data[$names[$thisNum]]) ? $data[$names[$thisNum]] : [];
                    $thisNum++;

                    if ($num > $thisNum && count($data) > 0) {
                        $fib($thisNum);
                    }
                }
            };

            $fib();

            self::$names[$key] = $data;
        }

        return self::$names[$key];

    }

    /**
     * 获取文件配置信息
     * @date   2019-06-13T16:19:55+0800
     * @author ChenMingjiang
     * @param  string                   $path    [数组则 合并多个配置文件]
     * @param  array                    $options [特殊参数]
     *                                           root 根目录
     * @return [type]                   [description]
     */
    public static function includes($path = 'config.php', $options = [])
    {

        $paths = is_array($path) ? $path : (array) $path;

        $root = isset($options['root']) ? $options['root'] : CONFIG_PATH;

        $md5 = md5(json_encode($paths));

        if (!isset(self::$includes[$md5])) {

            self::$includes[$md5] = [];

            foreach ($paths as $item) {
                $onePath = $root . ($item ?: 'config.php');

                $oneMd5 = md5($item);

                if (!isset(self::$includes[$oneMd5])) {
                    if (is_file($onePath)) {
                        self::$includes[$oneMd5] = include_once $onePath;
                    } else {
                        self::$includes[$oneMd5] = [];
                    }
                }

                self::$includes[$md5] = self::$includes[$md5] ? array_merge(self::$includes[$md5], self::$includes[$oneMd5]) : self::$includes[$oneMd5];

            }
        }

        return self::$includes[$md5];

    }

    public static function IP()
    {
        $ip = '0.0.0.1';
        if (getenv('HTTP_CLIENT_IP')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif (getenv('HTTP_X_FORWARDED_FOR')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('HTTP_X_FORWARDED')) {
            $ip = getenv('HTTP_X_FORWARDED');
        } elseif (getenv('HTTP_FORWARDED_FOR')) {
            $ip = getenv('HTTP_FORWARDED_FOR');
        } elseif (getenv('HTTP_FORWARDED')) {
            $ip = getenv('HTTP_FORWARDED');
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip   = $long ? $ip : '0.0.0.1';

        return $ip;
    }
}
