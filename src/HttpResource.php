<?php
//------------------------
//· Http资源类
//-------------------------
namespace denha;

class HttpResource
{
    public static $request; // 请求资源

    public function __construct()
    {
        if (!self::$request) {
            self::$request['service']         = $_SERVER;
            self::$request['params']['get']   = self::get();
            self::$request['params']['post']  = self::post();
            self::$request['params']['put']   = self::put();
            self::$request['params']['files'] = self::files();
        }
    }

    public static function get($name = null, $type = '', $default = '')
    {
        $data = null;
        if ($name === null) {
            foreach ($_GET as $key => $val) {
                if (!is_array($val)) {
                    $val        = trim($val);
                    $data[$key] = !get_magic_quotes_gpc() ? htmlspecialchars(addslashes($val), ENT_QUOTES, 'UTF-8') : htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
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

        if ($name && !is_array($data)) {
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
    public static function put($name, $type = '', $default = '')
    {
        // if (!post($name, $type, $default)) {
        //     parse_str(file_get_contents('php://input'), $_POST);
        // }

        // return post($name, $type, $default);
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
                    $data[$key] = !get_magic_quotes_gpc() ? htmlspecialchars(addslashes($val), ENT_QUOTES, 'UTF-8') : htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
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

        if ($name && !is_array($data)) {
            $data = self::filter($data, $type, $default);
        }

        return isset($data) ? $data : '';
    }

    /**
     * 过滤参数
     * @date   2018-07-02T11:23:49+0800
     * @author ChenMingjiang
     * @param  [type]                   $data    [值]
     * @param  [type]                   $type    [类型]
     * @param  [type]                   $default [默认值]
     * @return [type]                            [description]
     */
    public static function filter($data, $type = 'intval', $default = '')
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
