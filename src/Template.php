<?php
//------------------------
//· 模板类
//-------------------------
namespace denha;

use denha\view\Native;
use denha\view\Vue;

class Template
{
    public static $config;

    public static function parseContent($config = [])
    {
        // 重新加载配置文件
        self::config($config);

        switch (self::$config['template']) {
            case 'Native':
                $view = new Native(self::$config);
                break;
            case 'Vue':
                $view = new Vue(self::$config);
                break;
            default:
                # code...
                break;
        }

        // 执行解析模板方法
        return $view->parseFile();
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
