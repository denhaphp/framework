 <?php

use denha\Route;

if (!function_exists('GSF')) {
    function GSF($array, $v)
    {
        foreach ($array as $key => $value) {
            if (!is_array($key)) {
                GSC($key, $v);
            } else {
                GSF($key, $v);
            }

            if (!is_array($value)) {
                GSC($value, $v);
            } else {
                GSF($value, $v);
            }
        }
    }
}

if (!function_exists('GSC')) {
    function GSC($str, $v)
    {
        foreach ($v as $key => $value) {
            if ((preg_match('/' . $value . '/is', $str) == 1) || (preg_match('/' . $value . '/is', urlencode($str)) == 1)) {
                abort('您的请求带有不合法参数!', 404);
            }
        }
    }
}

if (!function_exists('abort')) {
    /**
     * 调试输出
     * @date   2018-05-21T14:40:16+0800
     * @author ChenMingjiang
     * @param  [type]                   $msg  [description]
     * @param  string                   $code [description]
     * @return [type]                         [description]
     */
    function abort($msg, $code = '200')
    {
        return denha\HttpTrace::abort($msg, $code);
    }
}

if (!function_exists('auth')) {
    /**
     * 字符串加密、解密函数
     * @param     string    $txt          字符串
     * @param     string    $operation    ENCODE为加密，DECODE为解密，可选参数，默认为ENCODE，
     * @param     string    $key          密钥：数字、字母、下划线
     * @param     string    $expiry       过期时间
     * @return    string
     */
    function auth($string, $operation = 'ENCODE', $key = '', $expiry = 0)
    {
        $key = $key ? $key : config('auth_key');

        $ckey_length = 4;
        $key         = md5($key != '' ? $key : config('auth_key'));
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
}

if (!function_exists('config')) {
    /**
     * 获取配置基础信息
     * @date   2018-07-12T17:08:40+0800
     * @author ChenMingjiang
     * @param  string                   $name [description]
     * @param  string                   $path [description]
     * @return [type]                         [description]
     */
    function config($name = '', $path = '')
    {

        $nameArr = explode('.', $name);

        if ($path) {
            $data = getConfig($path, $name);
        } else {
            if ($name && count($nameArr) == 1) {
                $data = denha\Start::$config[$name];
            } elseif ($name && count($nameArr) > 1) {
                $data = denha\Start::$config[$nameArr[0]];
                foreach ($nameArr as $key => $value) {
                    if ($key) {
                        $data = $data[$value];
                    }
                }
            } else {
                $data = denha\Start::$config;
            }
        }

        return $data;
    }
}

if (!function_exists('cookie')) {
    /**
     * Cookie操作
     * @date   2018-07-12T17:08:01+0800
     * @author ChenMingjiang
     * @param  string                   $name    [description]
     * @param  string                   $value   [description]
     * @param  array                    $options [description]
     * @return [type]                            [description]
     */
    function cookie($name = '', $value = '', $options = array())
    {

        $config = array_merge(denha\Start::$config['cookie'], array_change_key_case((array) $options));

        if (!$name) {
            return false;
        }

        $name   = $config['prefix'] ? $config['prefix'] . $name : $name;
        $expire = $config['expire'] ? TIME + $config['expire'] : 0;

        if (is_array($value)) {
            $value = json_encode($value);
        }

        if ($value === '') {
            if (isset($_COOKIE[$name])) {
                $data = $_COOKIE[$name];
                $data = $config['auth'] ? auth($data, 'DECODE') : $data;
                if (stripos($data, '{') !== false) {
                    $data = json_decode($data, true);
                }
            }

            return isset($data) ? $data : '';
        }

        //内容加密
        if ($config['auth']) {
            $value = auth($value);
        }

        setcookie($name, $value, $expire, $config['path'], $config['domain']);
    }
}

if (!function_exists('dao')) {
    /**
     * [Dao方法助手函数]
     * @date   2018-05-21T14:43:21+0800
     * @author ChenMingjiang
     * @param  [type]                   $name [description]
     * @param  string                   $app  [description]
     * @return [type]                         [description]
     */
    function dao($name, $app = '')
    {
        static $_dao = array();

        if (!$app) {
            $class = 'dao\\base\\' . $name;
        } else {
            $class = 'dao\\' . $app . '\\' . $name;
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

    }
}

if (!function_exists('enUnicode')) {
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
}

if (!function_exists('zipStr')) {
    /**
     * [压缩字符串]
     * @date   2018-07-12T17:06:15+0800
     * @author ChenMingjiang
     * @param  [type]                   $value     [description]
     * @param  string                   $operation [ENCODE为加密，DECODE为解密]
     * @return [type]                              [description]
     */
    function zipStr($value, $operation = 'ENCODE')
    {
        if ($operation == 'ENCODE') {
            $value = is_array($value) ? json_encode($value) : $value;
            $value = gzcompress($value, 9);
            $value = base64_encode($value);
            $value = str_replace(array('+', '/', '='), array('-', '_', ''), $value);
        } elseif ($operation == 'DECODE') {
            $value = str_replace(array('-', '_', ''), array('+', '/', '='), $value);

            $value = base64_decode($value);
            $value = gzuncompress($value);
        }

        return $value;

    }
}

if (!function_exists('files')) {
    /**
     * 获取fiels
     * @date   2018-07-12T17:06:35+0800
     * @author ChenMingjiang
     * @param  [type]                   $name [description]
     * @return [type]                         [description]
     */
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
}

if (!function_exists('filterParam')) {
    /**
     * 过滤参数
     * @date   2018-07-02T11:23:49+0800
     * @author ChenMingjiang
     * @param  [type]                   $data    [description]
     * @param  [type]                   $type    [description]
     * @param  [type]                   $default [description]
     * @return [type]                            [description]
     */
    function filterParam($data, $type = 'intval', $default = '')
    {

        if (!is_array($data)) {
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
}

if (!function_exists('get')) {
    /**
     * GET过滤
     * @date   2018-07-12T17:10:04+0800
     * @author ChenMingjiang
     * @param  [type]                   $name    [description]
     * @param  string                   $type    [description]
     * @param  string                   $default [description]
     * @return [type]                            [description]
     */
    function get($name, $type = '', $default = '')
    {
        $data = null;
        if ($name == 'all') {
            foreach ($_GET as $key => $val) {
                if (!is_array($val)) {
                    $val        = trim($val);
                    $data[$key] = !get_magic_quotes_gpc() ? htmlspecialchars(addslashes($val), ENT_QUOTES, 'UTF-8') : htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
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

        if ($name != 'all' && !is_array($data)) {
            $data = filterParam($data, $type, $default);
        }
        return $data;
    }
}

if (!function_exists('getVar')) {
    /**
     * 获取配置常量
     * -------------------
     * | getVar('tags','console.article') 获取 appliaction/console/tools/var/article文件中的 tags.$ext 文件
     * | getVar('tags','article') 获取 appliaction/tools/var/article文件中的 tags.$ext 文件
     * -------------------
     * @date   2018-07-12T17:10:33+0800
     * @author ChenMingjiang
     * @param  [type]                   $filename [description]
     * @param  [type]                   $path     [description]
     * @param  [type]                   $ext      [description]
     * @return [type]                             [description]
     */
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
}

if (!function_exists('getConfig')) {
    /**
     * 获取config下配置文档
     * @date   2018-07-12T17:13:26+0800
     * @author ChenMingjiang
     * @param  string                   $path [路径]
     * @param  string                   $name [文件名称]
     * @return [type]                         [description]
     */
    function getConfig($path = 'config', $name = '')
    {
        static $_configData = array();

        if (!isset($_configData[$path])) {

            if (is_file(CONFIG_PATH . $path . '.php')) {
                $_configData[$path] = include CONFIG_PATH . $path . '.php';
            }

        }

        if (isset($_configData[$path])) {
            if ($name === null) {
                return $_configData[$path];
            }

            if (isset($_configData[$path][$name])) {
                return $_configData[$path][$name];
            }
        }

        return null;
    }
}

if (!function_exists('getIP')) {
    /**
     * 获取真实IP地址
     * @date   2018-07-12T17:13:17+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
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
}

if (!function_exists('getMaxDim')) {
    /**
     * 获取数组的维度
     * @date   2018-05-21T15:48:29+0800
     * @author ChenMingjiang
     * @param  [type]                   $vDim [description]
     * @return [type]                         [description]
     */
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
}

if (!function_exists('isWritable')) {
    /**
     * 如果没有写入权限尝试修改权限 如果修改后还是失败 则跳过
     * @date   2018-05-21T14:43:39+0800
     * @author ChenMingjiang
     * @param  [type]                   $path [description]
     * @return boolean                        [description]
     */
    function isWritable($path)
    {

        if (!is_writable($path) && $path) {
            chmod($path, 0755);
            if (!is_writable($path)) {
                return false;
            } else {
                return true;
            }
        }

        return true;
    }
}

if (!function_exists('imgUrl')) {
    /**
     * 获取上传图片地址
     * @date   2018-07-12T17:12:35+0800
     * @author ChenMingjiang
     * @param  [type]                   $name [图片名称]
     * @param  string                   $path [图片地址]
     * @param  integer                  $size [description]
     * @param  boolean                  $host [description]
     * @return [type]                         [description]
     */
    function imgUrl($name, $path = '', $size = 0, $host = false)
    {

        if (stripos($name, ',') !== false && !is_array($name)) {
            $imgName = explode(',', $name);
        } else {
            $imgName = is_array($name) ? $name : (array) $name;
        }

        foreach ($imgName as $key => $value) {
            if (!$value) {
                $url = config('ststic') . '/default.png';
                $url = !$host ? $url : $host . $url;
            } else {
                if ($path) {
                    $url = config('uploadfile') . $path . '/' . $value;
                } else {
                    $url = config('uploadfile') . $value;
                }

                $url = !$host ? $url : $host . $url;

            }

            $data[] = $url;
        }

        $data = count($data) > 1 ? $data : current($data)
        ;
        return $data;
    }
}

if (!function_exists('response')) {

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
        $ssl    = isset($options['ssl']) ? $options['ssl'] : array();

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

        //设置请求头
        if (count($headers) > 0) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        // 证书认证
        if (!empty($options['ssl'])) {
            foreach ($options['ssl'] as $key => $value) {
                if (is_file($value)) {
                    if ($key == 'CERT') {
                        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
                        curl_setopt($ch, CURLOPT_SSLCERT, $value);
                    } elseif ($key == 'KEY') {
                        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                        curl_setopt($ch, CURLOPT_SSLKEY, $value);
                    }
                } else {
                    throw new Exception('Curl错误 : ssl证书文件地址错误 -- ' . $value);
                }

            }
        }

        curl_setopt($ch, CURLOPT_HEADER, 0); // 是否显示返回的Header区域内容
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
            print_r('-------输入参数options-----' . PHP_EOL);
            print_r($options);
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
}
if (!function_exists('strCut')) {
    /**
     * 字符串截取
     * @date   2018-07-14T22:59:51+0800
     * @author ChenMingjiang
     * @param  [type]                   $str     [字符串]
     * @param  integer                  $length  [截取长度]
     * @param  string                   $default [截取后显示后缀]
     * @return [type]                            [description]
     */
    function strCut($str, $length = 0, $default = '...')
    {

        if (mb_strlen($str) > $length) {
            $str = mb_substr($str, 0, $length) . $default;
        }

        return $str;
    }
}

if (!function_exists('session')) {
    /**
     * session操作
     * @date   2018-07-12T17:12:07+0800
     * @author ChenMingjiang
     * @param  string                   $name  [description]
     * @param  string                   $value [description]
     * @return [type]                          [description]
     */
    function session($name = '', $value = '')
    {

        // 启动session
        if (PHP_SESSION_ACTIVE != session_status()) {
            session_start();
        }

        //删除
        if ($value === null) {

            if (isset($_SESSION[$name])) {
                unset($_SESSION[$name]);
            }

            return true;
        }
        //读取session
        elseif ($value == '') {
            $data = isset($_SESSION[$name]) ? $_SESSION[$name] : '';
            return json_decode($data, true);
        }
        //保存
        else {
            // 数组
            if (is_array($name)) {
                foreach ($name as $k => $v) {
                    $_SESSION[$k] = json_encode($v);
                }
            }
            //二维数组
            else {
                $_SESSION[$name] = json_encode($value);
            }

            return true;
        }

        return false;
    }
}
if (!function_exists('table')) {
    /**
     * 生成唯一token
     * @date   2018-07-13T16:08:33+0800
     * @author ChenMingjiang
     * @param  string                   $name [验证名称]
     * @param  string                   $type [加密方式]
     * @return [type]                         [description]
     */
    function token($name = '__token__', $type = 'md5')
    {
        $type  = is_callable($type) ? $type : 'md5';
        $token = call_user_func($type, $_SERVER['REQUEST_TIME_FLOAT']);
        // if ($this->isAjax()) {
        // header($name . ': ' . $token);
        // }
        session($name, $token);
        return $token;
    }
}

if (!function_exists('table')) {
    /**
     * [数据库助手函数]
     * @date   2018-05-21T14:42:43+0800
     * @author ChenMingjiang
     * @param  [type]                   $name       [description]
     * @param  boolean                  $options    [description]
     * @return [type]                               [description]
     */
    function table($name, $options = [])
    {
        static $_do;

        if (is_null($_do)) {
            $_do = denha\db\BuildSql::getInstance(); //单例实例化
        }

        if ($name) {
            $_do = $_do->table($name, $options);
        } else {
            $_do = $_do;
        }

        return $_do;
    }
}

if (!function_exists('url')) {
    /**
     * 创建url
     * ------------------------
     * | {F:url()} to /MODULE/CONTROLLER/ACTION
     * | {F:url('xxxx')} to /MODULE/CONTROLLER/xxx
     * | {F:url('/aaa/bbb/ccc/ddd')} to /aaa/bbb/ccc/ddd
     * | {F:url('aaa/bbbb')} to /MODULE/aaa/bbb
     * ------------------------
     * @date   2018-07-06T10:50:29+0800
     * @author ChenMingjiang
     * @param  [type]                   $location [请求URL]
     * @param  array                    $params   [description]
     * @param  array                    $options  [description]
     * @return [type]                             [description]
     */
    function url($location = null, $params = [], $options = [])
    {

        $hostUrl = isset($options['host']) ? isset($options['host']) : URL; // 前缀域名
        $isGet   = isset($options['is_get']) ? isset($options['is_get']) : true; // 开启伪静态 true开启 false关闭

        // 外链直接返回
        if (stripos($location, 'http://') !== false || stripos($location, 'https://') !== false) {
            return $location;
        }

        if (stripos($location, '/s/') !== false) {
            $uri      = explode('/s/', $location);
            $location = $uri[0];

            if (!empty($uri[1])) {

                $urlParams = Route::changeGetValue($uri[1]);
                $params    = array_merge($params, $urlParams);

            }
        }

        if ($location === null || $location === '') {
            $routeUrl = '/' . str_replace('.', '/', MODULE) . '/' . CONTROLLER . '/' . ACTION;
        } elseif (stripos($location, '/') === false && $location != null) {
            $routeUrl = '/' . str_replace('.', '/', MODULE) . '/' . CONTROLLER . '/' . $location;
        } elseif (stripos($location, '/') !== false && stripos($location, '/') !== 0 && $location != null) {
            $routeUrl = '/' . str_replace('.', '/', MODULE) . '/' . $location;
        } elseif (stripos($location, '/') === 0) {
            $routeUrl = $location;
        } else {
            $routeUrl = $location;
        }

        $param = '';
        if (!empty($params)) {
            $isOne = true;
            foreach ($params as $key => $value) {
                if (!$isGet) {
                    if ($isOne && stripos($routeUrl, '?') === false) {
                        $param = '?' . $key . '=' . $value;
                    } else {
                        $param .= '&' . $key . '=' . $value;
                    }
                } else {
                    if ($isOne && stripos($routeUrl, '?') === false) {
                        $param .= '/s/' . $key . '/' . $value;
                    } else {
                        $param .= '/' . $key . '/' . $value;
                    }
                }

                $isOne = false;
            }
        }

        // 检查规则路由
        if (config('route.open_route')) {
            $uri = Route::getRouteChangeUrl($routeUrl . $param);
        } else {
            $uri = $routeUrl . $param;
        }

        return $hostUrl . $uri;
    }
}

if (!function_exists('parseName')) {
    /**
     * [parseName description]
     * @date   2018-07-12T17:02:36+0800
     * @author ChenMingjiang
     * @param  [type]                   $name [description]
     * @param  boolean                  $type [description]
     * @return [type]                         [description]
     */
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
}

if (!function_exists('post')) {
    /**
     * [POST过滤]
     * @date   2018-07-12T17:02:18+0800
     * @author ChenMingjiang
     * @param  [type]                   $name    [description]
     * @param  string                   $type    [description]
     * @param  string                   $default [description]
     * @return [type]                            [description]
     */
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
            $data = filterParam($data, $type, $default);
        }

        return isset($data) ? $data : '';
    }
}

if (!function_exists('put')) {
    /**
     * [put description]
     * @date   2018-07-12T17:02:12+0800
     * @author ChenMingjiang
     * @param  [type]                   $name    [description]
     * @param  string                   $type    [description]
     * @param  string                   $default [description]
     * @return [type]                            [description]
     */
    function put($name, $type = '', $default = '')
    {
        if (!post($name, $type, $default)) {
            parse_str(file_get_contents('php://input'), $_POST);
        }

        return post($name, $type, $default);
    }
}

if (!function_exists('ping')) {
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
}

if (!function_exists('isMobile')) {
    /**
     * 判断是否是手机访问
     * @date   2018-05-28T11:30:29+0800
     * @author ChenMingjiang
     * @return boolean                  [description]
     */
    function isMobile()
    {
        // 先检查是否为wap代理，准确度高
        if (!empty($_SERVER['HTTP_VIA']) && stristr($_SERVER['HTTP_VIA'], "wap")) {
            return true;
        }
        // 检查浏览器是否接受 WML.
        elseif (strpos(strtoupper($_SERVER['HTTP_ACCEPT']), "VND.WAP.WML") > 0) {
            return true;
        }
        //检查USER_AGENT
        elseif (preg_match('/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $_SERVER['HTTP_USER_AGENT'])) {
            return true;
        } else {
            return false;
        }
    }
}
