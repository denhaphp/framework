<?php
namespace denha\Htttp;

class Response
{
    public static function GSF($array, $v)
    {
        foreach ($array as $key => $value) {
            if (!is_array($key)) {
                gsc($key, $v);
            } else {
                gsf($key, $v);
            }

            if (!is_array($value)) {
                gsc($value, $v);
            } else {
                gsf($value, $v);
            }
        }
    }

    public static function GSC($str, $v)
    {
        foreach ($v as $key => $value) {
            if ((preg_match('/' . $value . '/is', $str) == 1) || (preg_match('/' . $value . '/is', urlencode($str)) == 1)) {
                die('您的请求带有不合法参数!');
            }
        }
    }

    public static function GSS($value)
    {
        $value = (is_array($value) ? array_map('GSS', $value) : stripslashes($value));
        return $value;
    }

    /**
     * 过滤GET POST参数
     * @date   2017-07-26T17:20:10+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public static function filter()
    {
        $urlArr  = array('xss' => '\=\+\/v(?:8|9|\+|\/)|\%0acontent\-(?:id|location|type|transfer\-encoding)');
        $argsArr = array('xss' => '[\'\\\'\;\*\<\>].*\bon[a-zA-Z]{3,15}[\s\\r\\n\\v\\f]*\=|\b(?:expression)\(|\<script[\s\\\\\/]|\<\!\[cdata\[|\b(?:eval|alert|prompt|msgbox)\s*\(|url\((?:\#|data|javascript)', 'sql' => '[^\{\s]{1}(\s|\b)+(?:select\b|update\b|insert(?:(\/\*.*?\*\/)|(\s)|(\+))+into\b).+?(?:from\b|set\b)|[^\{\s]{1}(\s|\b)+(?:create|delete|drop|truncate|rename|desc)(?:(\/\*.*?\*\/)|(\s)|(\+))+(?:table\b|from\b|database\b)|into(?:(\/\*.*?\*\/)|\s|\+)+(?:dump|out)file\b|\bsleep\([\s]*[\d]+[\s]*\)|benchmark\(([^\,]*)\,([^\,]*)\)|(?:declare|set|select)\b.*@|union\b.*(?:select|all)\b|(?:select|update|insert|create|delete|drop|grant|truncate|rename|exec|desc|from|table|database|set|where)\b.*(charset|ascii|bin|char|uncompress|concat|concat_ws|conv|export_set|hex|instr|left|load_file|locate|mid|sub|substring|oct|reverse|right|unhex)\(|(?:master\.\.sysdatabases|msysaccessobjects|msysqueries|sysmodules|mysql\.db|sys\.database_name|information_schema\.|sysobjects|sp_makewebtask|xp_cmdshell|sp_oamethod|sp_addextendedproc|sp_oacreate|xp_regread|sys\.dbms_export_extension)', 'other' => '\.\.[\\\\\/].*\%00([^0-9a-fA-F]|$)|%00[\'\\\'\.]');

        $httpReferer = empty($_SERVER['HTTP_REFERER']) ? array() : array($_SERVER['HTTP_REFERER']);
        $queryString = empty($_SERVER['QUERY_STRING']) ? array() : array($_SERVER['QUERY_STRING']);
        GSF($queryString, $urlArr);
        GSF($httpReferer, $argsArr);
        GSF($_GET, $argsArr);
        GSF($_POST, $argsArr);
        GSF($_COOKIE, $argsArr);

        if (MAGIC_QUOTES_GPC) {
            $_GET     = array_map('GSS', $_GET);
            $_POST    = array_map('GSS', $_POST);
            $_COOKIE  = array_map('GSS', $_COOKIE);
            $_REQUEST = array_map('GSS', $_REQUEST);
        }
    }

    //POST过滤
    public function post($name, $type = '', $default = '')
    {

        if ($name == 'all') {
            foreach ($_POST as $key => $val) {
                $val        = trim($val);
                $data[$key] = !get_magic_quotes_gpc() ? htmlspecialchars(addslashes($val), ENT_QUOTES, 'UTF-8') : htmlspecialchars($val, ENT_QUOTES, 'UTF-8');

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

        if ($name != 'all' && !is_array($data)) {
            switch ($type) {
                case 'intval':
                    $data = $data === '' ? intval($default) : intval($data);
                    break;
                case 'float':
                    $data = $data === '' ? floatval($default) : floatval($data);
                    break;
                case 'text':
                    $data = $data === '' ? strval($default) : strval($data);
                    break;
                case 'trim':
                    $data = $data === '' ? trim($default) : trim($data);
                    break;
                case 'bool':
                    $data = $data === '' ? (bool) $default : (bool) $data;
                    break;
                case 'json':
                    $data = $data === '' ? $default : json_decode($data, true);
                    break;
                case 'img':
                    $data = stripos($data, 'default') !== false ? $default : $data;
                    if (stripos($data, 'http') !== false || stripos($data, '/') !== false) {
                        $data = pathinfo($data, PATHINFO_BASENAME);
                    }
                    break;
                case 'time':
                    if (stripos($data, '-') !== false) {
                        $data = strtotime($data);
                    }
                    break;
                default:
                    # code...
                    break;
            }
        }
        return $data;
    }

    //GET过滤
    public function get($name, $type = '', $default = '')
    {
        $data = null;
        if ($name == 'all') {

            foreach ($_GET as $key => $val) {
                $val        = trim($val);
                $data[$key] = !get_magic_quotes_gpc() ? htmlspecialchars(addslashes($val), ENT_QUOTES, 'UTF-8') : htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
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

        if ($name != 'all' && !is_array($data)) {
            switch ($type) {
                case 'intval':
                    $data = $data === '' ? intval($default) : intval($data);
                    break;
                case 'float':
                    $data = $data === '' ? floatval($default) : floatval($data);
                    break;
                case 'text':
                    $data = $data === '' ? strval($default) : strval($data);
                    break;
                case 'trim':
                    $data = $data === '' ? trim($default) : trim($data);
                    break;
                case 'bool':
                    $data = $data === '' ? (bool) $default : (bool) $data;
                    break;
                case 'json':
                    $data = $data === '' ? $default : json_decode($data, true);
                    break;
                case 'jsonp':
                    $data = $data === '' ? $default : get('callback') . '(' . json_encode($data, true) . ')';
                case 'img':
                    $data = stripos($data, 'default') !== false ? $default : $data;
                    break;
                case 'time':
                    if (stripos($data, '-') !== false) {
                        $data = strtotime($data);
                    }
                    break;
                default:
                    # code...
                    break;
            }
        }
        return $data;
    }

    // PUT
    public function put($name, $type = '', $default = '')
    {
        if (!sefl::post($name, $type, $default)) {
            parse_str(file_get_contents('php://input'), $_POST);
        }

        return sefl::post($name, $type, $default);
    }

    public function files($name)
    {
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
     * curl模拟GET/POST/PUT/DELETE
     * @date   2018-01-11T14:24:16+0800
     * @author ChenMingjiang
     * @param  [type]                   $url    [请求网址]
     * @param  string                   $method [请求类型 GET/POST/PUT/DELETE]
     * @param  array                    $param  [请求超时]
     * @param  array                    $header [头标记]
     * @return [type]                           [description]
     */
    public function response($url, $method = 'GET', $param = array(), $headers = array(), $isJson = true, $debug = false)
    {

        $ch = curl_init(); //初始化curl

        switch ($method) {
            case 'GET':
                foreach ($param as $key => $value) {
                    if (stripos($url, '?') === false) {
                        $url .= '?' . $key . '=' . $value;
                    } else {
                        $url .= '&' . $key . '=' . $value;
                    }
                }
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $param); //设置请求体，提交数据包
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, CURLOPT_POSTFIELDS, $param); //设置请求体，提交数据包
                break;
            case 'DELETE':
                foreach ($param as $key => $value) {
                    if (stripos($url, '?') !== fasle) {
                        $url .= '?' . $key . '=' . $value;
                    } else {
                        $url .= '&' . $key . '=' . $value;
                    }
                }
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        curl_setopt($ch, CURLOPT_HEADER, 0); // 是否显示返回的Header区域内容
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); //设置请求头
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 请求超时时间
        curl_setopt($ch, CURLOPT_URL, $url); // 要访问的地址

        $data = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // 获取返回的状态码

        curl_close($ch); // 关闭CURL会话

        if ($debug) {
            print_r('-------输入参数Url-----' . PHP_EOL);
            print_r($url . PHP_EOL);
            print_r('-------END-----' . PHP_EOL);
            print_r('-------输入参数header-----' . PHP_EOL);
            print_r($headers);
            print_r('-------END-----' . PHP_EOL);
            print_r('-------请求Code-----' . PHP_EOL);
            print_r($code . PHP_EOL);
            print_r('-------END-----' . PHP_EOL);
            print_r('-------返回结果-----' . PHP_EOL);
            print_r($data . PHP_EOL);
            print_r('-------END-----' . PHP_EOL);
            die;
        }

        if ('200' == $code) {
            if ($isJson) {
                return json_decode($data, true);
            }

            return $data;
        } else {
            return curl_error($ch);
        }
    }
}
