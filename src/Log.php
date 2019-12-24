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
    private static $instance;
    private static $loggers;
    private static $config;
    private static $name;
    private static $level;
    private $handler;
    private static $ext = '.log';

    public static function setChannel($name)
    {
        self::$name = $name;

        if (is_null(self::$instance)) {
            self::$instance = new Log;
        }

        if (!isset(self::$config[$name])) {
            self::$config[$name] = Config::get('log.channels.' . $name);
        }

        if (!self::$config[$name]) {
            throw new Exception("Log channel not find", 1);

        }

        if (!isset(self::$loggers[$name])) {
            self::$loggers[$name] = new Logger($name);
        }

        return self::$instance;
    }

    /** [formatter 日志格式设置] */
    public function setformatter($data = "[%datetime%] %channel%.%level_name% %message% %context% %extra%", $type = 'line')
    {

        if (self::$config[self::$name]['type'] == 'File') {
            $this->streamHandler();
        }

        if ($type == 'line') {
            $this->handler->setFormatter(new LineFormatter($data . "\n", "Y-m-d H:i:s", true, true));
        }

        return $this;
    }

    /** 文件日志保存 */
    public function streamHandler()
    {
        // 每日递增模式
        if (self::$config[self::$name]['drive']['name'] == 'days') {
            $fileName      = self::$config[self::$name]['drive']['path'];
            $maxFiles      = self::$config[self::$name]['drive']['file_max'] ?? 0;
            $this->handler = new RotatingFileHandler($fileName . self::$ext, $maxFiles);
            $this->handler->setFilenameFormat('{date}', 'Y-m-d');
        }
        // 单文件模式
        elseif (self::$config[self::$name]['drive']['name'] == 'single') {
            $fileName      = self::$config[self::$name]['drive']['path'] . (self::$config[self::$name]['single']['drive']['file_name'] ?: self::$name);
            $this->handler = new StreamHandler($fileName . self::$ext);
        }

        return $this;
    }

    /** 推送 */
    public function push()
    {
        // 保存进内存中
        if (!self::$config[self::$name]['realtime'] && HttpResource::getMethod() != 'CLI') {
            $this->handler = new BufferHandler($this->handler);
        }

        self::$loggers[self::$name]->pushHandler($this->handler);

        return $this;
    }

    public function __call($name, $arguments)
    {
        self::__callStatic($name, $arguments);
    }

    public static function __callStatic($name, $arguments)
    {

        $message   = $arguments[0] ?: '';
        $context   = $arguments[1] ?: [];
        $levelName = $arguments[2] ?: '';

        // 获取默认配置
        if (!self::$name) {
            self::setChannel('Denha')->setformatter()->push();
        }

        // 过滤日志记录类型
        if (self::limitLevel($name)) {
            self::$loggers[self::$name]->$name($message, $context);
        }

    }

    /** 过滤日志记录类型 */
    public static function limitLevel($levelName)
    {
        if (!in_array($levelName, self::$config[self::$name]['level'])) {
            return false;
        }

        return true;
    }

    /**
     * 创建日志
     * @param $name
     * @return mixed
     */
    private static function createLogger($name)
    {
        if (empty(self::$loggers[$name])) {
            // 根据业务域名与方法名进行日志名称的确定
            $category = $_SERVER['SERVER_NAME'];
            // 日志文件目录
            $fileName = self::$fileName;
            // 日志保存时间
            $maxFiles = self::$maxFiles;
            // 日志等级
            $level = self::$level;
            // 权限
            $filePermission = self::$filePermission;

            // 创建日志
            $logger = new Logger($category);
            // 日志文件相关操作
            $handler = new RotatingFileHandler("{$fileName}{$name}.log", $maxFiles, $level, true, $filePermission);
            // 日志格式
            $formatter = new LineFormatter("%datetime% %channel%:%level_name% %message% %context% %extra%\n", "Y-m-d H:i:s", false, true);

            $handler->setFormatter($formatter);
            $logger->pushHandler($handler);

            self::$loggers[$name] = $logger;
        }
        return self::$loggers[$name];
    }
}
