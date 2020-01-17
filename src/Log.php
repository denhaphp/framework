<?php
/**
 * Created by PhpStorm.
 * User: j
 * Date: 2019-01-09
 * Time: 15:33
 */
namespace denha;

use denha\Config;
use denha\HttpResource;
use Monolog\Handler\BufferHandler;
use Monolog\Logger;

class Log
{
    private static $instance = [];
    private static $loggers;
    private static $config;

    /** 加载驱动类 */
    private static $handlerClass = [
        'FILE'    => \denha\log\File::class,
        'MONGODB' => \denha\log\MongoDB::class,
    ];

    private $id;
    private $links;
    private $handler;

    public function __construct($name, $id)
    {
        $this->name  = $name;
        $this->id    = $id;
        $this->links = self::getConfig($id);
    }

    public static function setChannel($name, $config = [])
    {

        $id = self::getId($name, $config);

        if (!isset(self::$instance[$id])) {

            self::setConfig($name, $config);

            self::$instance[$id] = new Log($name, $id);
            self::$loggers[$id]  = new Logger($name);

        }

        return self::$instance[$id];
    }

    public static function getId($name, $config = [])
    {
        if ($config) {
            $name .= '_' . md5(json_encode($config));
        }

        return $name;

    }

    public static function setConfig(string $name, array $config)
    {

        if (!self::$config) {
            self::$config = Config::includes('log.php');
        }

        if (!$config) {
            return false;
        }

        if (isset(self::$config[$name])) {
            self::$config[$name . '_' . md5(json_encode($config))] = array_merge(self::$config[$name], $config);
        } else {
            self::$config[$name] = $config;
        }

    }

    public function getConfig($name)
    {

        if (!self::$config[$name]) {
            throw new Exception("Log channel not find config from name :" . $name . json_encode(self::$config), 1);
        }

        return self::$config[$name];

    }

    public function hander()
    {
        // 加载对应驱动类
        if (!isset(self::$handlerClass[strtoupper($this->links['type'])])) {
            throw new Exception("Log Not Find Type:" . $this->links['type']);
        }

        $driver        = &self::$handlerClass[strtoupper($this->links['type'])];
        $this->handler = (new $driver($this->links, $this->name))->setHander()->setFormatter()->getHander();

        if (!$this->links['realtime'] && HttpResource::getMethod() != 'CLI') {
            $this->handler = new BufferHandler($this->handler);
        }

        self::$loggers[$this->id]->pushHandler($this->handler);
    }

    /** 过滤日志记录类型 */
    public function limitLevel($levelName)
    {
        if (!in_array($levelName, $this->links['level'])) {
            return false;
        }

        return true;
    }

    public function __call($name, $arguments)
    {

        $message   = $arguments[0] ?: '';
        $context   = $arguments[1] ?: [];
        $levelName = $arguments[2] ?: '';

        if ($this->limitLevel($name)) {
            if (!self::$loggers[$this->id]->getHandlers()) {
                $this->hander();
            }
            self::$loggers[$this->id]->$name($message, $context);
        }
    }

    public static function __callStatic($name, $arguments)
    {

        // 获取默认配置
        $denha = self::setChannel('Denha');
        if (!self::$loggers[$denha->id]->getHandlers()) {
            $denha->hander();
        }

        // 过滤日志记录类型
        if ($denha->limitLevel($name)) {
            self::$loggers[$denha->id]->$name(...$arguments);
        }
    }

}
