<?php
function GSF($array, $v)
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

function GSC($str, $v)
{
    foreach ($v as $key => $value) {
        if ((preg_match('/' . $value . '/is', $str) == 1) || (preg_match('/' . $value . '/is', urlencode($str)) == 1)) {
            die('您的请求带有不合法参数!');
        }
    }
}

function GSS($value)
{
    $value = (is_array($value) ? array_map('GSS', $value) : stripslashes($value));
    return $value;
}

function parseName($name, $type = false)
{
    //下划线转大写
    if ($type) {
        return ucfirst(preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, $name));
    }
    //大写转下划线小写
    else {
        return strtolower(trim(preg_replace('/[A-Z]/', '_\\0', $name), '_'));
    }
}

//POST过滤
function post($name, $type = '', $default = '')
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
function get($name, $type = '', $default = '')
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

function put($name, $type = '', $default = '')
{
    if (!post($name, $type, $default)) {
        parse_str(file_get_contents('php://input'), $_POST);
    }

    return post($name, $type, $default);
}

function files($name)
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
function response($url, $method = 'GET', $param = array(), $headers = array(), $options = array())
{

    $isJson = isset($options['is_json']) ? $options['is_json'] : true;
    $debug  = isset($options['debug']) ? $options['debug'] : false;

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
        print_r('-------Curl开启-----' . PHP_EOL);
        print_r('-------输入参数Method-----' . PHP_EOL);
        print_r($method . PHP_EOL);
        print_r('-------END-----' . PHP_EOL);
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
        return;
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

//判断文件是否存在
function existsUrl($url)
{

    if ($url == '') {return false;}
    if (stripos($url, 'http') === false) {
        $http = $_SERVER['SERVER_NAME'];
        $url  = 'http://' . $http . '/' . $url;
    }
    $opts = array(
        'http' => array(
            'timeout' => 30,
        ),
    );

    $context = stream_context_create($opts);
    $rest    = @file_data_contents($url, false, $context);

    if ($rest) {
        return true;
    } else {
        return false;
    }
}

function table($name, $isTablepre = true)
{
    static $_do;

    if (is_null($_do)) {
        $_do = denha\Mysqli::getInstance(); //单例实例化
    }

    if ($name) {
        $_do = $_do->table($name, $isTablepre);
    } else {
        $_do = $_do;
    }

    return $_do;
}

function dao($name, $app = '')
{
    static $_dao = array();

    if (!$app) {
        $class = 'app\\tools\\dao\\base\\' . $name;
    } else {
        $class = 'app\\tools' . '\\dao\\' . $app . '\\' . $name;
    }

    $value = md5($class);

    if (isset($_dao[$value])) {
        return $_dao[$value];
    } else {
        if (class_exists($class)) {
            $_dao[$value] = new $class();
            return $_dao[$value];
        }
    }
    throw new Exception('Dao方法：' . $class . '不存在');
    //die('Dao方法：' . $class . '不存在');
}

//包含文件
function comprise($path)
{
    include VIEW_PATH . $path . '.html';
}

//如果没有写入权限尝试修改权限 如果修改后还是失败 则跳过
function isWritable($path)
{
    if (!is_writable($path)) {
        chmod($path, 0755);
        if (!is_writable($path)) {
            return false;
        } else {
            return true;
        }
    }

    return true;
}

//获取配置常量
//getVar('tags','console.article') 获取 appliaction/console/tools/var/article文件中的 tags.$ext 文件
//getVar('tags','article') 获取 appliaction/tools/var/article文件中的 tags.$ext 文件
//获取配置常量
//getVar('tags','console.article') 获取 appliaction/console/tools/var/article文件中的 tags.$ext 文件
//getVar('tags','article') 获取 appliaction/tools/var/article文件中的 tags.$ext 文件
function getVar($filename, $path, $ext = EXT)
{
    static $_vars = array();

    if (!$filename) {
        return null;
    }

    $name = md5($filename . $path);
    if (isset($_vars[$name])) {
        return $_vars[$name];
    } else {
        if (($length = stripos($path, '.')) === false) {
            $filePath = APP_PATH . 'tools' . DS . 'var' . DS . 'base' . DS . $path . DS . $filename . $ext;
        } else {
            $filePath = APP_PATH . 'tools' . DS . 'var' . DS . substr($path, 0, $length) . DS . substr(strstr($path, '.'), 1) . DS . $filename . $ext;
        }

        if (is_file($filePath)) {
            $_vars[$name] = include $filePath;
            return $_vars[$name];
        }
    }

    return null;
}

/**
 * 获取配置基础信息
 * @date   2018-05-16T17:07:55+0800
 * @author ChenMingjiang
 * @return [type]                   [description]
 */
function config($name = '')
{
    if ($name) {
        $data = denha\Start::$config[$name];
    } else {
        $data = denha\Start::$config;
    }

    return $data;
}

//获取config下配置文档
function getConfig($path = 'config', $name = '')
{
    static $_configData = array();

    if (!isset($_configData[$path])) {
        if (is_file(CONFIG_PATH . $path . '.php')) {
            $_configData[$path] = include CONFIG_PATH . $path . '.php';
        }

    }

    if (isset($_configData[$path])) {
        if ($name === '') {
            return $_configData[$path];
        }

        if (isset($_configData[$path][$name])) {
            return $_configData[$path][$name];
        }
    }

    return null;
}

/**
 * 创建getUrl
 * @date   2017-10-11T15:44:44+0800
 * @author ChenMingjiang
 * @param  string                   $location [请求地址]
 * @param  array                    $params   [参数数组]
 * @param  boolean                  $isGet    [开启伪静态 true关闭 false开启]
 * @return [type]                             [description]
 */
function url($location = '', $params = array(), $url = '', $isGet = false)
{

    $locationUrl = MODULE ? $url . '/' . MODULE : $url;
    if ($location === '') {
        $locationUrl .= '/' . CONTROLLER . '/' . ACTION;
    } elseif (stripos($location, '/') === false && $location != '') {
        $locationUrl .= '/' . CONTROLLER . '/' . $location;
    } elseif (stripos($location, '/') === 0) {
        $locationUrl = $url . $location;
    } else {
        $locationUrl .= '/' . $location;
    }

    $param = '';
    if (!empty($params)) {
        foreach ($params as $key => $value) {
            if ($isGet) {
                if (key($params) === $key && stripos($locationUrl, '?') === false) {
                    $param = '?' . $key . '=' . $value;
                } else {
                    $param .= '&' . $key . '=' . $value;
                }
            } else {
                if (key($params) === $key && stripos($locationUrl, '?') === false) {
                    $param .= '/s/' . $key . '/' . $value;
                } else {
                    $param .= '/' . $key . '/' . $value;
                }
            }

        }
    }

    return $locationUrl . $param;
}

//保存Cookie
function cookie($name = '', $value = '', $expire = 3600, $encode = false)
{
    if (!$name) {
        return false;
    }

    if (is_array($value)) {
        $value = json_encode($value);
    }

    //加密
    $value = $encode ? auth($value) : $value;

    setcookie($name, $value, time() + $expire, '/');

}

//获取Cookie
function getCookie($name, $encode = false)
{
    $data = '';
    if (isset($_COOKIE[$name])) {
        $data = $_COOKIE[$name];

        $data = $encode ? auth($data, 'DECODE') : $data;
        if (stripos($data, '{') !== false) {
            $data = json_decode($data, true);
        }

    }

    return $data;
}

//获取上传图片地址
function imgUrl($name, $path = '', $size = 0, $host = false)
{

    if (stripos($name, ',') !== false && !is_array($name)) {
        $imgName = explode(',', $name);
    } else {
        $imgName = is_array($name) ? $name : (array) $name;
    }

    foreach ($imgName as $key => $value) {
        if (!$value) {
            $url = '/ststic/default.png';
            $url = !$host ? $url : $host . $url;
        } else {
            if ($path) {
                $url = '/uploadfile/' . $path . '/' . $value;
            } else {
                $url = '/uploadfile/' . $value;
            }

            $url = !$host ? $url : $host . $url;

            //这块有点影响网速 设置超时 后续会改为检测数据库
            /*$opts = array(
        'http' => array(
        'method'  => "GET",
        'timeout' => 1, //单位秒
        ),
        );

        if (!file_get_contents($url, false, stream_context_create($opts))) {
        $url = '/ststic/default.png';
        $url = !$host ? URL . $url : $host . $url;
        }*/

        }

        $data[] = $url;
    }

    $data = count($data) > 1 ? $data : current($data);
    return $data;
}

/**
 * 根骨图片地址获取到图片名称
 * @date   2017-10-27T08:53:23+0800
 * @author ChenMingjiang
 * @param  [type]                   $path [description]
 * @return [type]                         [description]
 */
function fromImgaUrlGetImgaName($path)
{
    (!$path && stripos($path, 'nd.jpg') === false) ?: (string) ltrim($param['thumb'], substr($param['thumb'], 0, strripos($param['thumb'], '/') + 1));
}

function imgFetch($path)
{
    (!$path && stripos($path, 'nd.jpg') === false) ?: (string) ltrim($param['thumb'], substr($param['thumb'], 0, strripos($param['thumb'], '/') + 1));
}

//保存Session
function session($name = '', $value = '')
{
    static $_sessionData = array();

    //删除
    if ($value === null) {
        session_start();
        if (isset($_SESSION[$name])) {
            unset($_SESSION[$name]);
        }
        session_write_close(); //关闭session
        $_sessionData = $_SESSION;
        return true;
    }
    //读取session
    elseif ($value == '') {
        if (!isset($_sessionData[$value])) {
            session_start();
            $_sessionData = $_SESSION;
            session_write_close();

        }

        $data = isset($_sessionData[$name]) ? $_sessionData[$name] : '';
        if (is_object($data)) {
            $data = (array) $data;
        }

        return $data;
    }
    //保存
    else {
        session_start();

        // 数组
        if (is_array($name)) {
            foreach ($name as $k => $v) {
                $_SESSION[$k] = $v;
            }
        }
        //二维数组
        elseif (is_array($value)) {
            foreach ($value as $k => $v) {
                $_SESSION[$name][$k] = $v;
            }
        } else {
            $_SESSION[$name] = $value;
        }

        $_sessionData = $_SESSION;

        //关闭session 可防止高并发下死锁问题
        session_write_close();
        return true;
    }

    return false;
}

/**
 * 转换其他编码成Unicode编码
 * @date   2017-10-10T15:24:33+0800
 * @author ChenMingjiang
 * @param  [type]                   $name [需要转换的内容]
 * @param  string                   $code [当前编码]
 * @return [type]                         [description]
 */
function enUnicode($name, $code = 'UTF-8')
{
    $name = iconv($code, 'UCS-2', $name);
    $len  = strlen($name);
    $str  = '';
    for ($i = 0; $i < $len - 1; $i = $i + 2) {
        $c  = $name[$i];
        $c2 = $name[$i + 1];
        if (ord($c) > 0) {
            //两个字节的文字
            $str .= '\u' . base_convert(ord($c), 10, 16) . str_pad(base_convert(ord($c2), 10, 16), 2, 0, STR_PAD_LEFT);
            //$str .= base_convert(ord($c), 10, 16).str_pad(base_convert(ord($c2), 10, 16), 2, 0, STR_PAD_LEFT);
        } else {
            $str .= '\u' . str_pad(base_convert(ord($c2), 10, 16), 4, 0, STR_PAD_LEFT);
            //$str .= str_pad(base_convert(ord($c2), 10, 16), 4, 0, STR_PAD_LEFT);
        }
    }
    $str = strtoupper($str); //转换为大写
    return $str;
}

/**
 * 转换Unicode编码为其他编码
 * @date   2017-10-10T15:24:54+0800
 * @author ChenMingjiang
 * @param  [type]                   $name [需要转换的内容]
 * @param  string                   $code [需要转换成的编码]
 * @return [type]                         [description]
 */
function deUnicode($name, $code = 'UTF-8')
{
    $name = strtolower($name);
    // 转换编码，将Unicode编码转换成可以浏览的utf-8编码
    $pattern = '/([\w]+)|(\\\u([\w]{4}))/i';
    preg_match_all($pattern, $name, $matches);
    if (!empty($matches)) {
        $name = '';
        for ($j = 0; $j < count($matches[0]); $j++) {
            $str = $matches[0][$j];
            if (strpos($str, '\\u') === 0) {
                $code  = base_convert(substr($str, 2, 2), 16, 10);
                $code2 = base_convert(substr($str, 4), 16, 10);
                $c     = chr($code) . chr($code2);
                $c     = iconv('UCS-2', $code, $c);
                $name .= $c;
            } else {
                $name .= $str;
            }
        }
    }
    return $name;
}

/**
 * 编码转换
 * @date   2017-08-27T16:07:41+0800
 * @author ChenMingjiang
 * @param  string                   $content  [需要转码的内容]
 * @param  string                   $mbEncode [需要转换成的编码]
 * @return [type]                             [description]
 */
function mbDetectEncoding($content = '', $mbEncode = "UTF-8")
{
    $encode = mb_detect_encoding($content, array("ASCII", "UTF-8", "GB2312", "GBK", "BIG5", "EUC-CN", "UCS2"));
    if ($encode != $mbEncode) {
        $encode  = $encode == "EUC-CN" ? "GB2312" : $encode;
        $content = mb_convert_encoding($content, $mbEncode, $encode);
    }

    return $content;
}

/**
 * ping封装
 * @date   2018-01-19T14:06:10+0800
 * @author ChenMingjiang
 * @param  [type]                   $address [description]
 * @return [type]                            [description]
 */
function ping($address)
{
    $status = -1;
    if (strcasecmp(PHP_OS, 'WINNT') === 0) {
        // Windows 服务器下
        $pingresult = exec("ping -n 1 {$address}", $outcome, $status);
    } elseif (strcasecmp(PHP_OS, 'Linux') === 0) {
        // Linux 服务器下
        $pingresult = exec("ping -c 1 {$address}", $outcome, $status);
    }
    if (0 == $status) {
        $status = true;
    } else {
        $status = false;
    }
    return $status;
}

/**
 * 字符串加密、解密函数
 *
 *
 * @param    string    $txt        字符串
 * @param    string    $operation    ENCODE为加密，DECODE为解密，可选参数，默认为ENCODE，
 * @param    string    $key        密钥：数字、字母、下划线
 * @param    string    $expiry        过期时间
 * @return    string
 */
function auth($string, $operation = 'ENCODE', $key = '', $expiry = 0)
{
    $key = $key ? $key : \denha\Start::$config['authKey'];

    $ckey_length = 4;
    $key         = md5($key != '' ? $key : getConfig('config', 'authKey'));
    $keya        = md5(substr($key, 0, 16));
    $keyb        = md5(substr($key, 16, 16));
    $keyc        = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

    $cryptkey   = $keya . md5($keya . $keyc);
    $key_length = strlen($cryptkey);

    $string        = $operation == 'DECODE' ? base64_decode(strtr(substr($string, $ckey_length), '-_', '+/')) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
    $string_length = strlen($string);

    $result = '';
    $box    = range(0, 255);

    $rndkey = array();
    for ($i = 0; $i <= 255; $i++) {
        $rndkey[$i] = ord($cryptkey[$i % $key_length]);
    }

    for ($j = $i = 0; $i < 256; $i++) {
        $j       = ($j + $box[$i] + $rndkey[$i]) % 256;
        $tmp     = $box[$i];
        $box[$i] = $box[$j];
        $box[$j] = $tmp;
    }

    for ($a = $j = $i = 0; $i < $string_length; $i++) {
        $a       = ($a + 1) % 256;
        $j       = ($j + $box[$a]) % 256;
        $tmp     = $box[$a];
        $box[$a] = $box[$j];
        $box[$j] = $tmp;
        $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
    }

    if ($operation == 'DECODE') {
        if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
            return substr($result, 26);
        } else {
            return '';
        }
    } else {
        return $keyc . rtrim(strtr(base64_encode($result), '+/', '-_'), '=');
    }
}

/**
 * 根据PHP各种类型变量生成唯一标识号
 * @param mixed $mix 变量
 * @return string
 */
function toGuidString($mix)
{
    if (is_object($mix)) {
        return spl_object_hash($mix);
    } elseif (is_resource($mix)) {
        $mix = get_resource_type($mix) . strval($mix);
    } else {
        $mix = serialize($mix);
    }
    return md5($mix);
}

//唯一id
function guid()
{
    if (function_exists('com_create_guid')) {
        return com_create_guid();
    } else {
        mt_srand((double) microtime() * 10000); //optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45); // "-"
        $uuid   = chr(123) // "{"
         . substr($charid, 0, 8) . $hyphen
        . substr($charid, 8, 4) . $hyphen
        . substr($charid, 12, 4) . $hyphen
        . substr($charid, 16, 4) . $hyphen
        . substr($charid, 20, 12)
        . chr(125); // "}"
        return $uuid;
    }
}

//获取真实IP地址
function getIP()
{
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
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip   = $long ? $ip : '0.0.0.1';

    return $ip;
}

//获取用户浏览器版本信息
function getBrowser($agent = '')
{
    $agent ?: $agent = $_SERVER['HTTP_USER_AGENT'];
    $browseragent    = ''; //浏览器
    $browserversion  = ''; //浏览器的版本
    if (ereg('MSIE ([0-9].[0-9]{1,2})', $agent, $version)) {
        $browserversion = $version[1];
        $browseragent   = "Internet Explorer";
    } else if (ereg('Opera/([0-9]{1,2}.[0-9]{1,2})', $agent, $version)) {
        $browserversion = $version[1];
        $browseragent   = "Opera";
    } else if (ereg('Firefox/([0-9.]{1,5})', $agent, $version)) {
        $browserversion = $version[1];
        $browseragent   = "Firefox";
    } else if (ereg('Chrome/([0-9.]{1,3})', $agent, $version)) {
        $browserversion = $version[1];
        $browseragent   = "Chrome";
    } else if (ereg('Safari/([0-9.]{1,3})', $agent, $version)) {
        $browseragent   = "Safari";
        $browserversion = "";
    } else {
        $browserversion = "";
        $browseragent   = "Unknown";
    }
    return $browseragent . " " . $browserversion . ' ';
}

//获取用户操作系统
function getSystem($agent = '')
{
    $agent ?: $agent = $_SERVER['HTTP_USER_AGENT'];
    $browserplatform == '';
    if (eregi('win', $agent) && strpos($agent, '95')) {
        $browserplatform = "Windows 95";
    } elseif (eregi('win 9x', $agent) && strpos($agent, '4.90')) {
        $browserplatform = "Windows ME";
    } elseif (eregi('win', $agent) && ereg('98', $agent)) {
        $browserplatform = "Windows 98";
    } elseif (eregi('win', $agent) && eregi('nt 5.0', $agent)) {
        $browserplatform = "Windows 2000";
    } elseif (eregi('win', $agent) && eregi('nt 5.1', $agent)) {
        $browserplatform = "Windows XP";
    } elseif (eregi('win', $agent) && eregi('nt 6.0', $agent)) {
        $browserplatform = "Windows Vista";
    } elseif (eregi('win', $agent) && eregi('nt 6.1', $agent)) {
        $browserplatform = "Windows 7";
    } elseif (eregi('win', $agent) && ereg('32', $agent)) {
        $browserplatform = "Windows 32";
    } elseif (eregi('win', $agent) && eregi('nt', $agent)) {
        $browserplatform = "Windows NT";
    } elseif (eregi('Mac OS', $agent)) {
        $browserplatform = "Mac OS";
    } elseif (eregi('linux', $agent)) {
        $browserplatform = "Linux";
    } elseif (eregi('unix', $agent)) {
        $browserplatform = "Unix";
    } elseif (eregi('sun', $agent) && eregi('os', $agent)) {
        $browserplatform = "SunOS";
    } elseif (eregi('ibm', $agent) && eregi('os', $agent)) {
        $browserplatform = "IBM OS/2";
    } elseif (eregi('Mac', $agent) && eregi('PC', $agent)) {
        $browserplatform = "Macintosh";
    } elseif (eregi('PowerPC', $agent)) {
        $browserplatform = "PowerPC";
    } elseif (eregi('AIX', $agent)) {
        $browserplatform = "AIX";
    } elseif (eregi('HPUX', $agent)) {
        $browserplatform = "HPUX";
    } elseif (eregi('NetBSD', $agent)) {
        $browserplatform = "NetBSD";
    } elseif (eregi('BSD', $agent)) {
        $browserplatform = "BSD";
    } elseif (ereg('OSF1', $agent)) {
        $browserplatform = "OSF1";
    } elseif (ereg('IRIX', $agent)) {
        $browserplatform = "IRIX";
    } elseif (eregi('FreeBSD', $agent)) {
        $browserplatform = "FreeBSD";
    } elseif (stripos($agent, 'iphone') || stripos($agent, 'ipad')) {
        $browserplatform = 'ios';
    } elseif (stripos($agent, 'android')) {
        $browserplatform = 'android';
    } elseif (stripos($agent, 'MicroMessenger')) {
        $browserplatform = 'MicroMessenger';
    }

    if ($browserplatform == '') {$browserplatform = "Unknown";}
    return $browserplatform . ' ';
}

//百度转（腾讯/高德/谷歌）坐标转换
function baiduToTenxun($lat, $lng)
{
    $x_pi  = 3.14159265358979324 * 3000.0 / 180.0;
    $x     = $lng - 0.0065;
    $y     = $lat - 0.006;
    $z     = sqrt($x * $x + $y * $y) - 0.00002 * sin($y * $x_pi);
    $theta = atan2($y, $x) - 0.000003 * cos($x * $x_pi);
    $lng   = $z * cos($theta);
    $lat   = $z * sin($theta);
    return array('lng' => $lng, 'lat' => $lat);
}

//（腾讯/高德/谷歌）转百度坐标转换
function tenxunToBaidu($lat, $lng)
{
    $x_pi  = 3.14159265358979324 * 3000.0 / 180.0;
    $x     = $lng;
    $y     = $lat;
    $z     = sqrt($x * $x + $y * $y) + 0.00002 * sin($y * $x_pi);
    $theta = atan2($y, $x) + 0.000003 * cos($x * $x_pi);
    $lng   = $z * cos($theta) + 0.0065;
    $lat   = $z * sin($theta) + 0.006;
    return array('lng' => $lng, 'lat' => $lat);

}

// 获取数组的维度
function getMaxDim($vDim)
{
    if (!is_array($vDim)) {
        return 0;
    } else {
        $max1 = 0;
        foreach ($vDim as $item1) {
            $t1 = getmaxdim($item1);
            if ($t1 > $max1) {
                $max1 = $t1;
            }

        }
        return $max1 + 1;
    }
}

//获取汉字首字母
function getFirstCharter($str)
{
    header("content-Type: text/html; charset=GB2312");
    if (empty($str)) {
        return '';
    }

    $fchar = ord($str{0});
    if ($fchar >= ord('A') && $fchar <= ord('z')) {
        return strtoupper($str{0});
    }

    //$s1 = iconv("UTF-8", "gb2312//IGNORE", $str);
    //$s2 = iconv("gb2312", "UTF-8//IGNORE", $s1);

    $s1 = mb_convert_encoding($str, "GBK", "UTF-8");
    $s2 = mb_convert_encoding($s1, "UTF-8", "GBK");

    $s = $s2 == $str ? $s1 : $str;

    $asc = current(unpack('N', "\xff\xff$s"));
    //$asc = ord($s{0}) * 256 + ord($s{1}) - 65536;

    if ($asc >= -20319 && $asc <= -20284) {
        return 'A';
    }

    if ($asc >= -20283 && $asc <= -19776) {
        return 'B';
    }

    if ($asc >= -19775 && $asc <= -19219) {
        return 'C';
    }

    if ($asc >= -19218 && $asc <= -18711) {
        return 'D';
    }

    if ($asc >= -18710 && $asc <= -18527) {
        return 'E';
    }

    if ($asc >= -18526 && $asc <= -18240) {
        return 'F';
    }

    if ($asc >= -18239 && $asc <= -17923) {
        return 'G';
    }

    if ($asc >= -17922 && $asc <= -17418) {
        return 'H';
    }

    if ($asc >= -17417 && $asc <= -16475) {
        return 'J';
    }

    if ($asc >= -16474 && $asc <= -16213) {
        return 'K';
    }

    if ($asc >= -16212 && $asc <= -15641) {
        return 'L';
    }

    if ($asc >= -15640 && $asc <= -15166) {
        return 'M';
    }

    if ($asc >= -15165 && $asc <= -14923) {
        return 'N';
    }

    if ($asc >= -14922 && $asc <= -14915) {
        return 'O';
    }

    if ($asc >= -14914 && $asc <= -14631) {
        return 'P';
    }

    if ($asc >= -14630 && $asc <= -14150) {
        return 'Q';
    }

    if ($asc >= -14149 && $asc <= -14091) {
        return 'R';
    }

    if ($asc >= -14090 && $asc <= -13319) {
        return 'S';
    }

    if ($asc >= -13318 && $asc <= -12839) {
        return 'T';
    }

    if ($asc >= -12838 && $asc <= -12557) {
        return 'W';
    }

    if ($asc >= -12556 && $asc <= -11848) {
        return 'X';
    }

    if ($asc >= -11847 && $asc <= -11056) {
        return 'Y';
    }

    if ($asc >= -11055 && $asc <= -10247) {
        return 'Z';
    }

    if ($asc == -9559) {
        return 'O';
    }

    return null;
}
