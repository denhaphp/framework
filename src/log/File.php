<?php
/**
 * Created by PhpStorm.
 * User: j
 * Date: 2019-01-09
 * Time: 15:33
 */
namespace denha\log;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;

class File
{
    private $ext = '.log';
    private $config;
    private $name;
    private $handler;

    public function __construct(array $config, string $name)
    {
        $this->config = $config;
        $this->name   = $name;
    }

    public function setHander($config = [])
    {
        // 每日递增模式
        if ($this->config['drive']['name'] == 'daily') {
            $fileName      = $this->config['drive']['path'];
            $maxFiles      = $this->config['drive']['file_max'] ?? 0;
            $this->handler = new RotatingFileHandler($fileName . $this->ext, $maxFiles);
            $this->handler->setFilenameFormat('{date}', 'Y-m-d');
        }
        // 单文件模式
        elseif ($this->config['drive']['name'] == 'single') {
            $fileName      = $this->config['drive']['path'] . ($this->config['single']['drive']['file_name'] ?: $this->name);
            $this->handler = new StreamHandler($fileName . $this->ext);
        }

        return $this;
    }

    public function setFormatter($output = null, $dateFormat = '', $type = null)
    {
        $type       = $type ?: ($this->config['formatter']['type'] ?? 'line');
        $output     = $output ?: ($this->config['formatter']['output'] ?? '');
        $dateFormat = $dateFormat ?: ($this->config['formatter']['date_format'] ?? '');

        $formatter = new LineFormatter($output . "\n", $dateFormat, true, true);

        $this->handler->setFormatter($formatter);

        return $this;
    }

    public function getHander()
    {
    	return $this->handler;
    }

}
