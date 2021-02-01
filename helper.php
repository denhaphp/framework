<?php

use denha\Cache;
use denha\Config;
use denha\Controller;
use denha\Db;
use denha\HttpResource;
use denha\Route;

if (!function_exists('abort')) {
    /**
     * 调试输出.
     *
     * @date   2018-05-21T14:40:16+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $msg  [description]
     * @param string $code [description]
     *
     * @return [type] [description]
     */
    function abort($msg, $code = '200')
    {
        return denha\HttpTrace::abort($msg, $code);
    }
}

if (!function_exists('auth')) {
    /**
     * 字符串加密、解密函数.
     *
     * @param string $operation ENCODE为加密，DECODE为解密，可选参数，默认为ENCODE，
     * @param string $key       密钥：数字、字母、下划线
     * @param string $expiry    过期时间
     * @param mixed  $string
     *
     * @return string
     */
    function auth($string, $operation = 'ENCODE', $key = '', $expiry = 0)
    {
        $key = $key ?: config('auth_key');

        $ckey_length = 4;
        $key         = md5('' !== $key ? $key : config('auth_key'));

        if (!$key) {
            return false;
        }

        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ('DECODE' === $operation ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';

        $cryptkey   = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);

        $string        = 'DECODE' === $operation ? base64_decode(strtr(substr($string, $ckey_length), '-_', '+/'), true) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);

        $result = '';
        $box    = range(0, 255);

        $rndkey = [];
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

        if ('DECODE' === $operation) {
            if ((0 === substr($result, 0, 10) || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) === substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            }

            return '';
        }

        return $keyc . rtrim(strtr(base64_encode($result), '+/', '-_'), '=');
    }
}

if (!function_exists('config')) {
    /**
     * 获取配置基础信息.
     *
     * @date   2018-07-12T17:08:40+0800
     *
     * @author ChenMingjiang
     *
     * @param string $name [请求key值]
     * @param string $path [请求conf文件 默认config.php]
     *
     * @return [type] [description]
     */
    function config($name = null, $path = '')
    {
        return Config::get($name, $path);
    }
}

if (!function_exists('cookie')) {
    /**
     * Cookie操作.
     *
     * @date   2018-07-12T17:08:01+0800
     *
     * @author ChenMingjiang
     *
     * @param string $name    [名称]
     * @param string $value   [值]
     * @param array  $options [description]
     *                        prefix 名称前缀
     *                        expire 过期时间
     *                        domain 作用域
     *                        secure  => true || false
     *                        httponly => true || false
     *                        samesite => None || Lax  || Strict 默认Lax
     *                        auth true:加密 false:不加密 默认加密
     *
     * @return [type] [description]
     */
    function cookie($name = '', $value = '', $options = [])
    {
        // 合并配置信息
        $config = array_merge(Config::get('cookie'), array_change_key_case((array) $options));

        if (!$name) {
            return false;
        }

        $name = $config['prefix'] ? $config['prefix'] . $name : $name;

        if (is_array($value)) {
            $value = json_encode($value);
        }

        if ('' === $value) {
            if (isset($_COOKIE[$name])) {
                $data = $_COOKIE[$name];
                $data = isset($config['auth']) && $config['auth'] ? auth($data, 'DECODE') : $data;
                if (false !== stripos($data, '{')) {
                    $data = json_decode($data, true);
                }
            }

            return isset($data) ? $data : '';
        }

        // 内容加密
        if (isset($config['auth']) && $config['auth']) {
            $value = auth($value);
        }

        $setOptions = [
            'expires'  => isset($config['expire']) && $config['expire'] ? (TIME + $config['expire']) : 0,
            'path'     => $config['path'] ?? '/',
            'domain'   => $config['domain'] ?? '',
            'httponly' => $config['httponly'] ?? false,
            'secure'   => $config['secure'] ?? false,
            'samesite' => $config['samesite'] ?? '',
        ];

        setcookie($name, $value, $setOptions);
    }
}

if (!function_exists('dao')) {
    /**
     * dao助手方法.
     *
     * @date   2019-12-03T17:33:19+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $name [description]
     *
     * @return [type] [description]
     */
    function dao($name)
    {
        static $_dao = [];

        if (false === stripos($name, '.')) {
            $class = 'dao\\base\\' . $name;
        } else {
            $class = 'dao\\' . str_replace('.', '\\', $name);
        }

        $value = md5($class);

        if (isset($_dao[$value])) {
            return $_dao[$value];
        }
        if (class_exists($class)) {
            $_dao[$value] = new $class();

            return $_dao[$value];
        }

        throw new Exception('Dao方法：' . $class . '不存在');
    }
}

if (!function_exists('enUnicode')) {
    /**
     * 转换其他编码成Unicode编码
     *
     * @date   2017-10-10T15:24:33+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $name [需要转换的内容]
     * @param string $code [当前编码]
     *
     * @return [type] [description]
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
                $str .= '\u' . base_convert(ord($c), 10, 16) . str_pad(base_convert(ord($c2), 10, 16), 2, 0, \STR_PAD_LEFT);
                //$str .= base_convert(ord($c), 10, 16).str_pad(base_convert(ord($c2), 10, 16), 2, 0, STR_PAD_LEFT);
            } else {
                $str .= '\u' . str_pad(base_convert(ord($c2), 10, 16), 4, 0, \STR_PAD_LEFT);
                //$str .= str_pad(base_convert(ord($c2), 10, 16), 4, 0, STR_PAD_LEFT);
            }
        }
        $str = strtoupper($str); //转换为大写

        return $str;
    }
}

if (!function_exists('zipStr')) {
    /**
     * [压缩字符串].
     *
     * @date   2018-07-12T17:06:15+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $value     [字符串值]
     * @param string $operation [ENCODE为加密，DECODE为解密]
     *
     * @return [type] [description]
     */
    function zipStr($value, $operation = 'ENCODE')
    {
        if ('ENCODE' === $operation) {
            $value = base64_encode(gzcompress(serialize($value), 9));
            $value = str_replace(['+', '/', '='], ['-', '_', ''], $value);
        } elseif ('DECODE' === $operation) {
            $value = str_replace(['-', '_', ''], ['+', '/', '='], $value);
            $value = unserialize(gzuncompress(base64_decode($value, true)));
        }

        return $value;
    }
}

if (!function_exists('files')) {
    /**
     * 获取fiels.
     *
     * @date   2018-07-12T17:06:35+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $name [description]
     *
     * @return [type] [description]
     */
    function files($name)
    {
        return HttpResource::files($name);
    }
}

if (!function_exists('filterParam')) {
    /**
     * 过滤参数.
     *
     * @date   2018-07-02T11:23:49+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $value   [description]
     * @param [type] $type    [description]
     * @param [type] $default [description]
     *
     * @return [type] [description]
     */
    function filterParam($value, $type = 'intval', $default = '')
    {
        return HttpResource::filter($value, $type, $default);
    }
}

if (!function_exists('get')) {
    /**
     * GET过滤.
     *
     * @date   2018-07-12T17:10:04+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $name    [名称]
     * @param string $type    [过滤类型]
     * @param string $default [默认值]
     *
     * @return [type] [description]
     */
    function get($name = null, $type = '', $default = '')
    {
        if (isset(HttpResource::$request['params']['get'][$name])) {
            return HttpResource::filter(HttpResource::$request['params']['get'][$name], $type, $default);
        }

        return HttpResource::get($name, $type, $default);
    }
}

if (!function_exists('getVar')) {
    /**
     * 获取配置常量
     * -------------------
     * | getVar('console.article.tags') 获取 appliaction/console/tools/var/article文件中的 tags.$ext 文件
     * | getVar('article.tags') 获取 appliaction/tools/var/article文件中的 tags.$ext 文件
     * -------------------.
     *
     * @date   2018-07-12T17:10:33+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $path    [description]
     * @param mixed  $options
     *
     * @return [type] [description]
     */
    function getVar($path, $options = [])
    {
        $ext = isset($options['ext']) ? $options['ext'] : EXT;
        $dir = isset($options['dir']) ? $options['dir'] : APP_PATH . 'tools' . DS . 'var' . DS;

        static $_vars = [];

        $name = md5($path);

        if (isset($_vars[$name])) {
            return $_vars[$name];
        }

        $path     = str_replace('.', DS, $path);
        $filePath = $dir . $path . $ext;

        if (is_file($filePath)) {
            $_vars[$name] = include $filePath;

            return $_vars[$name];
        }

        return null;
    }
}

if (!function_exists('getConfig')) {
    /**
     * 获取配置文件.
     *
     * @date   2018-07-12T17:13:17+0800
     *
     * @author ChenMingjiang
     *
     * @param mixed $path
     *
     * @return [type] [description]
     */
    function getConfig($path)
    {
        return Config::includes($path);
    }
}

if (!function_exists('getMaxDim')) {
    /**
     * 获取数组的维度.
     *
     * @date   2018-05-21T15:48:29+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $vDim [description]
     *
     * @return [type] [description]
     */
    function getMaxDim($vDim)
    {
        if (!is_array($vDim)) {
            return 0;
        }
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

if (!function_exists('imgExists')) {
    /**
     * 判断图片是否存在.
     *
     * @date   2019-01-09T17:52:30+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $imgUrl [description]
     *
     * @return [type] [description]
     */
    function imgExists($imgUrl)
    {
        $ch = curl_init($imgUrl);
        // 不取回数据
        curl_setopt($ch, \CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, \CURLOPT_NOBODY, true);
        // 发送请求
        $result = curl_exec($ch);
        $code   = curl_getinfo($ch, \CURLINFO_HTTP_CODE); // 获取返回的状态码
        curl_close($ch); // 关闭CURL会话

        if (200 === $code) {
            return true;
        }

        return false;
    }
}

if (!function_exists('imgUrl')) {
    /**
     * 获取上传图片地址
     *
     * @date   2018-07-12T17:12:35+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $name [图片名称]
     * @param string $path [图片地址]
     * @param int    $size [图片尺寸]
     * @param bool   $host [description]
     *
     * @return [type] [description]
     */
    function imgUrl($name, $path = '', $size = '', $host = false)
    {
        $data = [];
        if (false !== stripos($name, ',') && !is_array($name)) {
            $imgName = explode(',', $name);
        } else {
            $imgName = is_array($name) ? $name : (array) $name;
        }

        foreach ($imgName as $imgName) {
            if (!$imgName) {
                $url = HttpResource::getHost() . Config::get('ststic') . '/default.png';
            } elseif ($size) {
                $url = zipimg($imgName, $path, $size);
            } else {
                if ($path) {
                    $url = $path . '/' . $imgName;
                } else {
                    $url = $imgName;
                }

                if ($host && $imgName) {
                    $url = $host . $url;
                } else {
                    $url = HttpResource::getHost() . Config::get('uploadfile') . $url;
                }
            }

            $data[] = $url;
        }

        $data = count($data) > 1 ? $data : current($data);

        return $data;
    }
}

if (!function_exists('zipImg')) {
    /**
     * 图片压缩.
     *
     * @date   2018-06-25T20:37:55+0800
     *
     * @author ChenMingjiang
     *
     * @param string $name [图片名称]
     * @param array  $path [图片地址]
     * @param array  $size [图片尺寸]
     *
     * @return [type] [description]
     */
    function zipImg($name, $path = '', $size = '')
    {
        $ext        = pathinfo($name, \PATHINFO_EXTENSION);
        $zipImgName = basename($name, '.' . $ext) . '_' . $size . '.' . $ext;

        // 如果不存在缓存 缩略图则创建缩略图
        $cacheLists = Cache::get('zipimglists');
        if (!isset($cacheLists[md5($zipImgName)])) {
            // 如何原图是否存在 根据原图=>创建新的缩略图
            $url = $path ? HttpResource::getHost() . config('uploadfile') . $path . '/' . $name : HttpResource::getHost() . config('uploadfile') . $name;

            if (imgExists($url)) {
                $size   = explode('x', $size);
                $width  = $size[0];
                $height = !empty($size[1]) ? $size[1] : 0;
                $res    = dao('File')->zipImg($url, $width, $height)['status'];

                if ($res) {
                    // 保留缓存记录
                    $cacheLists[md5($zipImgName)] = $zipImgName;
                    Cache::set('zipimglists', $cacheLists);

                    $url = HttpResource::getHost() . config('uploadfile') . 'zipimg/' . $zipImgName;
                }
            } else {
                $url = HttpResource::getHost() . config('ststic') . '/default.png';
            }
        } else {
            $url = HttpResource::getHost() . config('uploadfile') . 'zipimg/' . $cacheLists[md5($zipImgName)];
        }

        return $url;
    }
}

if (!function_exists('view')) {
    /**
     * 模板渲染.
     *
     * @date   2018-06-25T20:37:55+0800
     *
     * @author ChenMingjiang
     *
     * @param string $viewPath      [视图地址]
     * @param array  $viewParamData [渲染变量值]
     * @param array  $options       [预定义参数]
     *                              trace:单个视图关闭调试模式 【默认】true：开启 fasle：关闭
     *                              peg：自定义路径
     *
     * @return [type] [description]
     */
    function view($viewPath, $viewParamData = [], $options = [])
    {
        $Controller = new Controller();

        $this->show($viewPath, $viewParamData, $options);
    }
}

if (!function_exists('response')) {
    /**
     * curl模拟
     *
     * @date   2019-01-11T11:56:24+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $url     [请求地址]
     * @param string $method  [请求类型 GET/POST/PUT/DELETE]
     * @param array  $param   [请求参数]
     * @param array  $headers [请求头部信息]
     * @param array  $options [配置项]
     *                        isJson(bool)：是否返回json数据 默认是
     *                        debug(bool)： 是否开启调试模式 默认否
     *                        ssl(array)：证书认证地址
     *                        is_code(bool):是否返回请求页面状态码
     *                        out_time(int):指定超时时间 默认10秒
     *
     * @return [type] [description]
     */
    function response(string $url, string $method = 'GET', $param = [], array $headers = [], array $options = [])
    {
        $isJson  = $options['is_json'] ?? true;
        $debug   = $options['debug'] ?? false;
        $ssl     = $options['ssl'] ?? [];
        $isCode  = $options['is_code'] ?? false;
        $outTime = $options['out_time'] ?? 10;

        $ch = curl_init(); // 初始化curl

        switch ($method) {
            case 'GET':
                foreach ($param as $key => $value) {
                    if (false === stripos($url, '?')) {
                        $url .= '?' . $key . '=' . $value;
                    } else {
                        $url .= '&' . $key . '=' . $value;
                    }
                }

                break;
            case 'POST':
                curl_setopt($ch, \CURLOPT_POST, 1);
                curl_setopt($ch, \CURLOPT_POSTFIELDS, $param); //设置请求体，提交数据包

                break;
            case 'PUT':
                curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, 'PUT');
                curl_setopt($ch, \CURLOPT_POSTFIELDS, $param); //设置请求体，提交数据包

                break;
            case 'DELETE':
                foreach ($param as $key => $value) {
                    if (fasle !== stripos($url, '?')) {
                        $url .= '?' . $key . '=' . $value;
                    } else {
                        $url .= '&' . $key . '=' . $value;
                    }
                }
                curl_setopt($ch, \CURLOPT_CUSTOMREQUEST, 'DELETE');

                break;
        }

        // 设置请求头
        if (count($headers) > 0) {
            curl_setopt($ch, \CURLOPT_HTTPHEADER, $headers);
        }

        // 证书认证
        if (!empty($options['ssl'])) {
            foreach ($options['ssl'] as $key => $value) {
                if (is_file($value)) {
                    if ('CERT' === $key) {
                        curl_setopt($ch, \CURLOPT_SSLCERTTYPE, 'PEM');
                        curl_setopt($ch, \CURLOPT_SSLCERT, $value);
                    } elseif ('KEY' === $key) {
                        curl_setopt($ch, \CURLOPT_SSLKEYTYPE, 'PEM');
                        curl_setopt($ch, \CURLOPT_SSLKEY, $value);
                    }
                } else {
                    throw new Exception('Curl错误 : ssl证书文件地址错误 -- ' . $value);
                }
            }
        }

        curl_setopt($ch, \CURLOPT_HEADER, 0); // 是否显示返回的Header区域内容
        curl_setopt($ch, \CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        curl_setopt($ch, \CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($ch, \CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($ch, \CURLOPT_TIMEOUT, $outTime); // 请求超时时间
        curl_setopt($ch, \CURLOPT_URL, $url); // 要访问的地址

        $data = curl_exec($ch);
        $code = curl_getinfo($ch, \CURLINFO_HTTP_CODE); // 获取返回的状态码

        if ($debug) {
            print_r('-------Curl开启-----' . \PHP_EOL);
            print_r('-------输入参数Url-----' . \PHP_EOL);
            print_r($url . \PHP_EOL);
            print_r('-------END-----' . \PHP_EOL);
            print_r('-------输入参数Method-----' . \PHP_EOL);
            print_r($method . \PHP_EOL);
            print_r('-------END-----' . \PHP_EOL);
            print_r('-------输入参数param-----' . \PHP_EOL);
            print_r($param);
            print_r('-------END-----' . \PHP_EOL);
            print_r('-------输入参数header-----' . \PHP_EOL);
            print_r($headers);
            print_r('-------END-----' . \PHP_EOL);
            print_r('-------输入参数options-----' . \PHP_EOL);
            print_r($options);
            print_r('-------END-----' . \PHP_EOL);
            print_r('-------请求Code-----' . \PHP_EOL);
            print_r($code . \PHP_EOL);
            print_r('-------END-----' . \PHP_EOL);
            print_r('-------返回结果-----' . \PHP_EOL);
            if (200 === $code) {
                print_r($data . \PHP_EOL);
            } else {
                print_r(curl_error($ch) . \PHP_EOL);
            }
            print_r('-------END-----' . \PHP_EOL);
        }

        if (200 === $code) {
            if ($isJson) {
                $res = json_decode($data, true);
            } else {
                $res = $data;
            }
        } else {
            $res = curl_error($ch);
        }

        // 返回请求code
        if ($isCode) {
            $res = ['code' => $code, 'data' => $res];
        }

        curl_close($ch); // 关闭CURL会话

        return $res;
    }
}
if (!function_exists('strCut')) {
    /**
     * 字符串截取.
     *
     * @date   2018-07-14T22:59:51+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $str     [字符串]
     * @param int    $length  [截取长度]
     * @param string $default [截取后显示后缀]
     *
     * @return [type] [description]
     */
    function strCut(string $str, int $length = 0, string $default = '...')
    {
        if (mb_strlen($str) > $length) {
            $str = mb_substr($str, 0, $length) . $default;
        }

        return $str;
    }
}

if (!function_exists('session')) {
    /**
     * session操作.
     *
     * @date   2018-07-12T17:12:07+0800
     *
     * @author ChenMingjiang
     *
     * @param string $name  [description]
     * @param string $value [description]
     *
     * @return [type] [description]
     */
    function session($name = '', $value = '')
    {
        // 启动session
        if (\PHP_SESSION_ACTIVE !== session_status()) {
            session_start([
                'cache_limiter' => 'nocache',
            ]);
        }

        //删除
        if (null === $value) {
            if (isset($_SESSION[$name])) {
                unset($_SESSION[$name]);
            }

            return true;
        }
        //读取session
        if ('' === $value) {
            $data = isset($_SESSION[$name]) ? $_SESSION[$name] : '';

            return json_decode($data, true);
        }
        //保存

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

        return false;
    }
}
if (!function_exists('table')) {
    /**
     * 生成唯一token.
     *
     * @date   2018-07-13T16:08:33+0800
     *
     * @author ChenMingjiang
     *
     * @param string $name [验证名称]
     * @param string $type [加密方式]
     *
     * @return [type] [description]
     */
    function token($name = '__token__', $type = 'md5')
    {
        $type  = is_callable($type) ? $type : 'md5';
        $token = call_user_func($type, $_SERVER['REQUEST_TIME_FLOAT']);
        session($name, $token);

        return $token;
    }
}

if (!function_exists('table')) {
    /**
     * [数据库助手函数].
     *
     * @date   2018-05-21T14:42:43+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $name    [description]
     * @param bool   $options [description]
     *
     * @return [type] [description]
     */
    function table($name = null, $options = [])
    {
        if ($name) {
            return Db::table($name, $options);
        }

        return Db::connection();
    }
}

if (!function_exists('url')) {
    /**
     * 创建url
     * ------------------------
     * | {:url()} to /MODULE/CONTROLLER/ACTION
     * | {:url('xxxx')} to /MODULE/CONTROLLER/xxx
     * | {:url('/aaa/bbb/ccc/ddd')} to /aaa/bbb/ccc/ddd
     * | {:url('aaa/bbbb')} to /MODULE/aaa/bbb
     * ------------------------.
     *
     * @date   2018-07-06T10:50:29+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $location [请求URL]
     * @param array  $params   [description]
     * @param array  $options  [description]
     *                         host 前缀域名
     *                         is_get 伪静态 true开启 false关闭
     *                         is_route 路由改写 true开启 false关闭
     *
     * @return [type] [description]
     */
    function url($location = null, $params = [], $options = [])
    {
        $hostUrl = $options['host'] ?? HttpResource::getHost(); // 前缀域名
        $isGet   = $options['is_get'] ?? true; // 开启伪静态 true开启 false关闭
        $isRoute = $options['is_route'] ?? true; // 开启路由改写 true开启 false关闭

        // 外链直接返回
        if (false !== stripos($location, 'http://') || false !== stripos($location, 'https://')) {
            return $location;
        }

        if (false !== stripos($location, '/s/')) {
            $uri      = explode('/s/', $location);
            $location = $uri[0];

            if (!empty($uri[1])) {
                $urlParams = Route::changeGetValue($uri[1]);
                $params    = array_merge($params, $urlParams);
            }
        }

        if (null === $location || '' === $location) {
            $routeUrl = '/' . str_replace('.', '/', HttpResource::getModuleName()) . '/' . parseName(HttpResource::getControllerName()) . '/' . parseName(HttpResource::getActionName());
        } elseif (false === stripos($location, '/') && null !== $location) {
            $routeUrl = '/' . str_replace('.', '/', HttpResource::getModuleName()) . '/' . parseName(HttpResource::getControllerName()) . '/' . $location;
        } elseif (false !== stripos($location, '/') && 0 !== stripos($location, '/') && null !== $location) {
            $routeUrl = '/' . str_replace('.', '/', HttpResource::getModuleName()) . '/' . $location;
        } elseif (0 === stripos($location, '/')) {
            $routeUrl = $location;
        } else {
            $routeUrl = $location;
        }

        $param = ''; // 参数url
        if (!empty($params) && is_array($params)) {

            if (!$isGet) {
                $explode = '&';
                $param   = false === stripos($routeUrl, '?') ? '?' : '&';
            } else {
                $explode = '/';
                $param   = false === stripos($routeUrl, '?') ? '/s/' : '/';
            }

            foreach ($params as $key => $value) {

                // 数组参数
                if (is_array($value)) {
                    $values = '';
                    foreach ($value as $field => $v) {
                        $values .= $key . '[' . $field . ']' . $explode . $v . $explode;
                    }
                }
                // 非数组参数
                else {
                    $values = $key . $explode . $value . $explode;
                }

                $param .= substr($param, -1, 1) === $explode ? $values : $explode . $values;

            }

        }

        // 检查规则路由
        if (config('route.open_route') && $isRoute) {
            $uri = Route::getRouteChangeUrl($routeUrl . $param);
        } else {
            $uri = $routeUrl . $param;
        }

        if (!$hostUrl) {
            return $uri;
        }
        if ('//' === $uri) {
            return $hostUrl;
        }

        return $hostUrl . $uri;
    }
}

if (!function_exists('parseName')) {
    /**
     * [parseName description].
     *
     * @date   2018-07-12T17:02:36+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $name [description]
     * @param bool   $type [true :下划线转大写 false:大写转下划线]
     *
     * @return [type] [description]
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
     * [POST过滤].
     *
     * @date   2018-07-12T17:02:18+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $name    [description]
     * @param string $type    [description]
     * @param string $default [description]
     *
     * @return [type] [description]
     */
    function post($name = null, $type = '', $default = '')
    {
        if (isset(HttpResource::$request['params']['post'][$name])) {
            return HttpResource::filter(HttpResource::$request['params']['post'][$name], $type, $default);
        }

        return HttpResource::post($name, $type, $default);
    }
}

if (!function_exists('params')) {
    /**
     * params过滤 可直接获取 GET POST参数.
     *
     * @date   2018-07-12T17:10:04+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $name    [description]
     * @param string $type    [description]
     * @param string $default [description]
     *
     * @return [type] [description]
     */
    function params($name = null, $type = '', $default = '')
    {
        $data = get($name, $type, null);
        if (null === $data) {
            $data = post($name, $type, $default);
        }

        return $data;
    }
}

if (!function_exists('put')) {
    /**
     * [put description].
     *
     * @date   2018-07-12T17:02:12+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $name    [description]
     * @param string $type    [description]
     * @param string $default [description]
     *
     * @return [type] [description]
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
     * ping封装.
     *
     * @date   2018-01-19T14:06:10+0800
     *
     * @author ChenMingjiang
     *
     * @param [type] $address [description]
     *
     * @return [type] [description]
     */
    function ping($address)
    {
        $status = -1;
        if (0 === strcasecmp(\PHP_OS, 'WINNT')) {
            // Windows 服务器下
            $pingresult = exec("ping -n 1 {$address}", $outcome, $status);
        } elseif (0 === strcasecmp(\PHP_OS, 'Linux')) {
            // Linux 服务器下
            $pingresult = exec("ping -c 1 {$address}", $outcome, $status);
        }
        if (0 === $status) {
            $status = true;
        } else {
            $status = false;
        }

        return $status;
    }
}

if (!function_exists('isMobile')) {
    /**
     * 判断是否是手机访问.
     *
     * @date   2018-05-28T11:30:29+0800
     *
     * @author ChenMingjiang
     *
     * @return bool [description]
     */
    function isMobile()
    {
        // 先检查是否为wap代理，准确度高
        if (!empty($_SERVER['HTTP_VIA']) && stristr($_SERVER['HTTP_VIA'], 'wap')) {
            return true;
        }
        // 检查浏览器是否接受 WML.
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtoupper($_SERVER['HTTP_ACCEPT']), 'VND.WAP.WML') > 0) {
            return true;
        }
        //检查USER_AGENT
        if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $_SERVER['HTTP_USER_AGENT'])) {
            return true;
        }

        return false;
    }
}
