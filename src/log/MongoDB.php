<?php
/**
 * Created by PhpStorm.
 * User: j
 * Date: 2019-01-09
 * Time: 15:33
 */
namespace denha\log;

use MongoDB\Driver\Manager as MongoDBClient;
use Monolog\Formatter\MongoDBFormatter;
use Monolog\Handler\MongoDBHandler;

class MongoDB
{

    public function __construct(array $config, string $name)
    {
        $this->config = $config;
        $this->name   = $name;
    }

    public function setHander()
    {

        $parts = [
            'mongodb://',
            ($this->config['drive']['username'] ?: ''),
            ($this->config['drive']['password'] ? ':' . $this->config['drive']['password'] : ''),
            ($this->config['drive']['username'] ? '@' : ''),
            $this->config['drive']['host'],
            ($this->config['drive']['port'] != '27017' ? ':' . $this->config['drive']['port'] : ''),
        ];

        $mongodb = new MongoDBClient(implode('', $parts), ['connectTimeoutMS' => ($this->config['drive']['timeout'] ?: 5) * 1000]);

        $this->handler = new MongoDBHandler($mongodb, ($this->config['dirve']['database'] ?: 'logs'), 'log');

        return $this;
    }

    public function setFormatter($output = null, $dateFormat = '', $type = null)
    {

        $output     = $output ?: ($this->config['formatter']['output'] ?? '');
        $dateFormat = $dateFormat ?: ($this->config['formatter']['date_format'] ?? '');

        $formatter = new MongoDBFormatter();

        $this->handler->setFormatter($formatter);

        return $this;
    }

    public function getHander()
    {
        return $this->handler;
    }
}
