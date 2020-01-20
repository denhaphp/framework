<?php
//------------------------
//· 模板类
//-------------------------

declare (strict_types = 1);

namespace denha;

class Template
{
    public static $config;

    /** 加载驱动类 */
    private static $handlerClass = [
        'Native' => \denha\view\Native::class,
        'Vue'    => \denha\view\Vue::class,
    ];

    public static function parseContent($config = [])
    {
        // 重新加载配置文件
        self::config($config);

        return (new self::$handlerClass[self::$config['template']](self::$config))->parseFile();

    }

    public static function config($config = [])
    {
        self::$config['left']     = isset($config['left']) ? $config['left'] : '{';
        self::$config['right']    = isset($config['right']) ? $config['right'] : '}';
        self::$config['suffix']   = isset($config['suffix']) ? $config['suffix'] : '.html'; // 模板后缀名
        self::$config['template'] = isset($config['template']) ? $config['template'] : 'Native'; // 模板渲染类名称
        self::$config['root']     = isset($config['root']) ? $config['root'] : VIEW_PATH; // 模板根目录
        self::$config['view']     = isset($config['view']) ? $config['view'] : ''; // 模板地址
        self::$config['data']     = isset($config['data']) ? $config['data'] : []; // 模板渲染变量

    }

}
