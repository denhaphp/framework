<?php
//------------------------
//· 缓存类
//-------------------------

declare (strict_types = 1);

namespace denha;

use denha\cache\CacheFactory;
use denha\Config;

class Cache
{

    public static function channel(string $key)
    {
        $config = Config::get('cache')[$key];
        if (!Config::get('cache')[$key]) {
            throw new Exception("Cache Config Name Not Find : cache." . $key);
        }

        return CacheFactory::message($config);
    }

    public static function create($config = [])
    {
        if (!$config) {
            $config = Config::get('cache');
            array_shift($config);
        }

        return CacheFactory::message($config);
    }

    public static function __callStatic($name, $options = [])
    {

        $names = ['set', 'get', 'delete', 'has', 'getMultiple', 'setMultiple', 'deleteMultiple', 'clear'];

        if (in_array($name, $names)) {
            return Cache::create()->$name(...$options);
        }

        if ($options) {
            if (!Config::get('cache')[$name]) {
                throw new Exception("Cache Config Name Not Find : cache." . $name);
            }

            $config = array_merge(Config::get('cache')[$name], $options);
            return Cache::create($config);
        }

        return Cache::channel($name);
    }

}
