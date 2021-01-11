<?php
//------------------------
//· Http资源类
//-------------------------

declare (strict_types = 1);

namespace denha;

class HttpResource
{
    public static $request; // 请求资源
    public static $instance; // 单例实例化;

    // 参数过滤规则
    const XSSARGS = [
        'xss'   => '[\'\\\'\;\*\<\>].*\bon[a-zA-Z]{3,15}[\s\\r\\n\\v\\f]*\=|\b(?:expression)\(|\<script[\s\\\\\/]|\<\!\[cdata\[|\b(?:eval|alert|prompt|msgbox)\s*\(|url\((?:\#|data|javascript)',

        'sql'   => '[^\{\s]{1}(\s|\b)+(?:select\b|update\b|insert(?:(\/\*.*?\*\/)|(\s)|(\+))+into\b).+?(?:from\b|set\b)|[^\{\s]{1}(\s|\b)+(?:create|delete|drop|truncate|rename|desc)(?:(\/\*.*?\*\/)|(\s)|(\+))+(?:table\b|from\b|database\b)|into(?:(\/\*.*?\*\/)|\s|\+)+(?:dump|out)file\b|\bsleep\([\s]*[\d]+[\s]*\)|benchmark\(([^\,]*)\,([^\,]*)\)|(?:declare|set|select)\b.*@|union\b.*(?:select|all)\b|(?:select|update|insert|create|delete|drop|grant|truncate|rename|exec|desc|from|table|database|set|where)\b.*(charset|ascii|bin|char|uncompress|concat|concat_ws|conv|export_set|hex|instr|left|load_file|locate|mid|sub|substring|oct|reverse|right|unhex)\(|(?:master\.\.sysdatabases|msysaccessobjects|msysqueries|sysmodules|mysql\.db|sys\.database_name|information_schema\.|sysobjects|sp_makewebtask|xp_cmdshell|sp_oamethod|sp_addextendedproc|sp_oacreate|xp_regread|sys\.dbms_export_extension)',

        'other' => '\.\.[\\\\\/].*\%00([^0-9a-fA-F]|$)|%00[\'\\\'\.]'];

    // 获取实例
    public static function initInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new HttpResource();
        }

        return self::$instance;
    }

    public function setRequest()
    {
        self::$request['service']         = $_SERVER;
        self::$request['method']          = self::getMethod();
        self::$request['params']['get']   = self::get();
        self::$request['params']['post']  = self::post();
        self::$request['params']['put']   = self::put();
        self::$request['params']['files'] = self::files();

        self::xssRule($_COOKIE); // 过滤Cookie信息

        return self::$request;
    }

    public static function isAjax()
    {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || !empty($_POST['ajax']) || !empty($_GET['ajax'])) {
            return true;
        }

        return false;
    }

    public static function getHttpType()
    {
        $type = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? 'https://' : 'http://';

        return $type;
    }

    public static function getHost()
    {
        return isset($_SERVER['HTTP_HOST']) ? self::getHttpType() . $_SERVER['HTTP_HOST'] : '';
    }

    public static function getUrl()
    {
        return self::getHost() . self::getUri();
    }

    public static function getRequest()
    {
        return self::$request;
    }

    /** 获取请求类型 */
    public static function getMethod()
    {
        if (PHP_SAPI == 'cli') {
            $method = 'CLI';
        } else {
            $method = strtoupper($_SERVER['REQUEST_METHOD']);
        }

        return $method;

    }

    public static function get($name = null, $type = '', $default = '')
    {
        $data = null;
        if ($name === null) {
            foreach ($_GET as $key => $val) {
                if (!is_array($val)) {
                    $val        = trim($val);
                    $data[$key] = htmlspecialchars(addslashes($val), ENT_QUOTES, 'UTF-8');
                } else {
                    $data[$key] = $val;
                }
            }
        } else {
            //数组信息通过 xx.xxx 来获取
            if (stripos($name, '.') !== false) {
                $name = explode('.', $name);
                $data = isset($_GET[$name[0]][$name[1]]) ? $_GET[$name[0]][$name[1]] : '';
            } else {
                $data = isset($_GET[$name]) ? $_GET[$name] : '';
            }
        }

        if ($name) {
            $data = self::filter($data, $type, $default);
        }

        
        return $data;
    }

    /**
     * 获取fiels
     * @date   2018-07-12T17:06:35+0800
     * @author ChenMingjiang
     * @param  [type]                   $name [description]
     * @return [type]                         [description]
     */
    public static function files($name = null)
    {

        // 返回全部
        if (!$name) {
            return $_FILES;
        }

        if (isset($_FILES[$name])) {
            if (is_array($_FILES[$name]['name'])) {
                foreach ($_FILES[$name] as $key => $value) {
                    foreach ($value as $k => $v) {
                        $data[$k][$key] = $v;
                    }
                }
            } else {
                $data = $_FILES[$name];
            }
        } else {
            $data = null;
        }

        return $data;
    }

    /**
     * [put description]
     * @date   2018-07-12T17:02:12+0800
     * @author ChenMingjiang
     * @param  [type]                   $name    [description]
     * @param  string                   $type    [description]
     * @param  string                   $default [description]
     * @return [type]                            [description]
     */
    public static function put($name = null, $type = '', $default = '')
    {
        // if (!post($name, $type, $default)) {
        //     parse_str(file_get_contents('php://input'), $_POST);
        // }

        // return post($name, $type, $default);
    }

    public static function setModule($name)
    {
        self::$request['module'] = $name;
    }

    public static function getModuleName()
    {
        return self::$request['module'] ?? '';
    }

    public static function setController($name)
    {
        self::$request['controller'] = $name;
    }

    public static function getControllerName()
    {
        return self::$request['controller'] ?? '';
    }

    public static function setAction($name)
    {
        self::$request['action'] = $name;
    }

    public static function getActionName()
    {
        return self::$request['action'] ?? '';
    }

    public static function setUri($value)
    {
        self::$request['uri'] = $value;
    }

    public static function getUri()
    {
        return self::$request['uri'] ?? '';
    }

    public static function setClass($name)
    {
        self::$request['class'] = $name;
    }

    public static function getClass()
    {
        return self::$request['class'] ?? '';
    }

    /**
     * [POST过滤]
     * @date   2018-07-12T17:02:18+0800
     * @author ChenMingjiang
     * @param  [type]                   $name    [description]
     * @param  string                   $type    [description]
     * @param  string                   $default [description]
     * @return [type]                            [description]
     */
    public static function post($name = null, $type = '', $default = '')
    {
        if ($name === null) {
            foreach ($_POST as $key => $val) {
                if (!is_array($val)) {
                    $val        = trim($val);
                    $data[$key] = htmlspecialchars(addslashes($val), ENT_QUOTES, 'UTF-8');
                } else {
                    $data[$key] = $val;
                }
            }
        } else {
            //数组信息通过 xx.xxx 来获取
            if (stripos($name, '.') !== false) {
                $name = explode('.', $name);
                $data = isset($_POST[$name[0]][$name[1]]) ? $_POST[$name[0]][$name[1]] : '';
            } else {
                $data = isset($_POST[$name]) ? $_POST[$name] : '';
            }
        }

        if ($name) {
            $data = self::filter($data, $type, $default);
        }

        return isset($data) ? $data : '';
    }

    /**
     * 过滤数据
     * @date   2019-08-30T11:18:46+0800
     * @author ChenMingjiang
     * @param  [type]                   $data    [值]
     * @param  string                   $type    [类型 intval:整型 float:浮点型 text:文本类型 trim:清空两边空白 bool:布尔类型 json:解析json implode:分割数组 img:图片类型 time:文本时间类型转时间戳 同一数据多种分割通过"."拼接按顺序执行]
     * @param  string                   $default [默认值]
     * @return [type]                   [description]
     */
    public static function filter($data, $types = 'intval', $default = '')
    {
        $types = explode('.', $types);

        foreach ($types as $type) {

            $data = self::parseFilter($data, $type, $default);
        }

        return $data;
    }

    /**
     * 过滤数据
     * @date   2019-08-30T11:18:46+0800
     * @author ChenMingjiang
     * @param  [type]                   $data    [值]
     * @param  string                   $type    [类型 intval:整型 float:浮点型 text:文本类型 trim:清空两边空白 bool:布尔类型 json:解析json implode:分割数组 img:图片类型 time:文本时间类型转时间戳]
     * @param  string                   $default [默认值]
     * @return [type]                   [description]
     */
    public static function parseFilter($data, $type = 'intval', $default = '')
    {

        // 如果default默认值为null 并且不存在值 则直接返回默认值null 不进行强制类型转移
        // 否则则强制将默认值转移成对应类型
        switch ($type) {
            case 'intval':
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        $data[$key] = $value === '' ? ($default === null ? null : intval($default)) : intval($value);
                    }
                } else {
                    $data = $data === '' ? ($default === null ? null : intval($default)) : intval($data);
                }
                break;
            case 'float':
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        $data[$key] = $value === '' ? ($default === null ? null : floatval($default)) : floatval($value);
                    }
                } else {
                    $data = $data === '' ? ($default === null ? null : floatval($default)) : floatval($data);
                }
                break;
            case 'text':
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        $data[$key] = $value === '' ? ($default === null ? null : strval($default)) : strval($value);
                    }
                } else {
                    $data = $data === '' ? ($default === null ? null : strval($default)) : strval($data);
                }
                break;
            case 'trim':
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        $data[$key] = $value === '' ? ($default === null ? null : trim($default)) : trim($value);
                    }
                } else {
                    $data = $data === '' ? ($default === null ? null : trim($default)) : trim($data);
                }
                break;
            case 'bool':
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        $data[$key] = $value === '' ? ($default === null ? null : (bool) $default) : (bool) $value;
                    }
                } else {
                    $data = $data === '' ? ($default === null ? null : (bool) $default) : (bool) $data;
                }
                break;
            case 'time':
                if (is_array($data)) {
                    foreach ($data as $key => $value) {
                        if (stripos($value, '-') !== false) {
                            $data[$key] = strtotime($value);
                        }
                    }
                } else {
                    if (stripos($data, '-') !== false) {
                        $data = strtotime($data);
                    }
                }
                break;
            case 'json': // 解析json数据
                $data = htmlspecialchars_decode(stripslashes($data));
                $data = $default === true || $default === '' ? json_decode($data, true) : $data;
                break;
            case 'implode': // 分割数组
                $data = $data === '' ? '' : implode($default ? $default : ',', (array) $data);
                break;
            case 'img':
                if (stripos($data, 'default') !== false) {
                    $data = $default;
                } else {
                    $imgArr = explode(',', $data);
                    $data   = [];
                    foreach ($imgArr as $img) {
                        if (stripos($img, 'http') !== false || stripos($img, '/') !== false) {
                            $data[] = pathinfo($img, PATHINFO_BASENAME);
                        } else {
                            $data[] = $img;
                        }
                    }
                    $data = implode(',', $data);
                }
                break;
            case 'html_decode': // 字符串反解析
                $data = htmlspecialchars_decode(stripslashes($data));
                break;
            default:
                # code...
                break;
        }

        return $data;
    }

    /**
     * 获取ip地址
     * @date   2020-03-30T14:43:05+0800
     * @author ChenMingjiang
     * @param  boolean                  $isLong [是否返回整型 false:否 true:是 默认否]
     */
    public static function ip($isLong = false)
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

        if ($isLong) {
            return sprintf("%u", ip2long($ip));
        }

        return $ip;
    }

    public static function xssRule($params)
    {
        $args = self::XSSARGS;

        $filter = function ($params) use (&$filter, $args) {
            foreach ($params as $key => $str) {
                if (is_array($key)) {
                    $filter($key);
                } elseif (is_array($str)) {
                    $filter($str);
                } else {
                    foreach ($args as $k => $item) {
                        if ((preg_match('/' . $item . '/is', $str) == 1) || (preg_match('/' . $item . '/is', urlencode($str)) == 1)) {
                            throw new Exception('you http params wrongful !!!');
                        }
                    }
                }
            }
        };

        $filter(is_array($params) ? $params : (array) $params);

    }

}
