<?php
//------------------------
//· Cookie
//-------------------------

declare (strict_types = 1);

namespace denha;

use denha\cache\CacheFactory;
use denha\Config;

class Cookie
{

    public static function channel(string $key)
    {
        $config = Config::get('cookie')[$key];
        if (!Config::get('cookie')[$key]) {
            throw new Exception("Cache Config Name Not Find : cookie." . $key);
        }

        $config['type'] = 'Cookie';

        return CacheFactory::message($config);
    }

    public static function create($config = [])
    {
        if (!$config) {
            $config = Config::get('cookie');
            $config = count($config) === count($config, 1) ? $config : array_shift($config);
        }

        $config['type'] = 'Cookie';

        return CacheFactory::message($config);
    }

    public static function __callStatic($name, $options = [])
    {

        return Cookie::create()->$name(...$options);

    }
}
