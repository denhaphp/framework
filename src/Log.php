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
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\BufferHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class Log
{
    private static $instance = [];
    private static $loggers;
    private static $config;
    private static $level;
    private static $ext = '.log';

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

        if (is_null(self::$instance[$id])) {

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

    /** [formatter 日志格式设置] */
    public function push($output = null, $dateFormat = '', $type = null)
    {

        $type       = $type ?: ($this->links['formatter']['type'] ?? 'line');
        $output     = $output ?: ($this->links['formatter']['output'] ?? '');
        $dateFormat = $dateFormat ?: ($this->links['formatter']['date_format'] ?? '');

        if ($this->links['type'] == 'File') {
            $this->streamHandler();
        }

        if ($type == 'line') {
            $this->handler->setFormatter(new LineFormatter($output . "\n", $dateFormat, true, true));
        }

        // 保存进内存中
        if (!$this->links['realtime'] && HttpResource::getMethod() != 'CLI') {
            $this->handler = new BufferHandler($this->handler);
        }

        self::$loggers[$this->id]->pushHandler($this->handler);

        return $this;
    }

    /** 文件日志保存 */
    public function streamHandler()
    {
        // 每日递增模式
        if ($this->links['drive']['name'] == 'daily') {
            $fileName      = $this->links['drive']['path'];
            $maxFiles      = $this->links['drive']['file_max'] ?? 0;
            $this->handler = new RotatingFileHandler($fileName . self::$ext, $maxFiles);
            $this->handler->setFilenameFormat('{date}', 'Y-m-d');
        }
        // 单文件模式
        elseif ($this->links['drive']['name'] == 'single') {
            $fileName      = $this->links['drive']['path'] . ($this->links['single']['drive']['file_name'] ?: $this->name);
            $this->handler = new StreamHandler($fileName . self::$ext);
        }

        return $this;

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
                $this->push();
            }
            self::$loggers[$this->id]->$name($message, $context);
        }
    }

    public static function __callStatic($name, $arguments)
    {

        $message   = $arguments[0] ?: '';
        $context   = $arguments[1] ?: [];
        $levelName = $arguments[2] ?: '';

        // 获取默认配置
        $denha = self::setChannel('Denha');
        if (!self::$loggers[$denha->id]->getHandlers()) {
            $denha->push();
        }

        // 过滤日志记录类型
        if ($denha->limitLevel($name)) {
            self::$loggers[$denha->id]->$name($message, $context);
        }

    }

}
