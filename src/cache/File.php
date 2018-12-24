<?php
//------------------------
// 文件缓存类
//-------------------------
namespace denha\cache;

class File
{
    public static $config;
    public static $path;
    public static $ext = '.text';
    public static $instance;

    public static function init()
    {
        if (!self::$config) {
            self::$config = config('cache');
            self::$path   = self::$config['dir'] ? self::$config['dir'] : DATA_CACHE_PATH;
            is_dir(self::$path) ? '' : mkdir(self::$path, 0755, true);
        }

        if (!self::$instance) {
            self::$instance = new File;
        }

        return self::$instance;
    }

    public function read($name)
    {
        $path = self::$path . md5($name) . self::$ext;
        if (is_file($path)) {
            $data                   = file_get_contents($path);
            list($content, $expire) = explode(':::', $data);
            // 过期删除
            if ($expire && $expire > TIME) {
                $this->del($name);
                $content = null;
            } else {
                $content = json_decode($content, true);
            }
        } else {
            $content = null;
        }

        return $content;
    }

    public function save($name, $value, $expire = 0)
    {
        $path    = self::$path . md5($name) . self::$ext;
        $content = json_encode($value) . ':::' . ($expire ? (TIME + $expire) : 0);
        file_put_contents($path, $content);
    }

    public function del($name)
    {
        $path = self::$path . md5($name) . self::$ext;
        unlink($path);
    }

}
