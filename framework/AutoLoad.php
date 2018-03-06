<?php
namespace denha;

class AutoLoad
{
    protected static $instance = array();
    // 类名映射
    protected static $map = array();

    // 命名空间别名
    protected static $namespaceAlias = array();

    // 自动加载
    public static function autoload($class)
    {
        if ($file = self::findFile($class)) {
            include $file;
        }
    }

    //查找文件
    private static function findFile($class)
    {
        $map       = strstr(trim($class), '\\');
        $namespace = substr($class, 0, strpos($class, '\\'));
        $class     = str_replace('\\', DS, ltrim($class, $namespace));

        if (isset(self::$namespaceAlias[$namespace])) {
            if (is_file(self::$namespaceAlias[$namespace] . $class . EXT)) {
                return self::$namespaceAlias[$namespace] . $class . EXT;
            } else {
                //print_r($class);
            }
        }
    }

    //  注册自动加载机制利用SPL自动加载器来注册loader
    public static function register($autoload = '')
    {

        // 注册系统自动加载
        spl_autoload_register($autoload ?: 'denha\\AutoLoad::autoload', true, true);
        // 注册命名空间定义方法
        self::addNamespace([
            'denha'  => FARM_PATH,
            'app'    => APP_PATH,
            'vendor' => APP_PATH,
        ]);

    }

    // 注册classmap
    public static function addClassMap($class, $map = '')
    {
        if (is_array($class)) {
            self::$map = array_merge(self::$map, $class);
        } else {
            self::$map[$class] = $map;
        }
    }

    // 注册命名空间
    public static function addNamespace($namespace, $path = '')
    {
        if (is_array($namespace)) {
            foreach ($namespace as $prefix => $paths) {
                self::addPsr4($prefix, rtrim($paths, DS), true);
            }
        } else {
            self::addPsr4($namespace, rtrim($path, DS), true);
        }
    }

    //添加命名空间
    public static function addPsr4($prefix, $path)
    {
        if (!isset(self::$namespaceAlias[$prefix])) {
            //var_dump($path);
            self::$namespaceAlias[$prefix] = $path;
        }
    }

}
