<?php
/** 数据库驱动 */
namespace denha\database;

use denha\Trace;
use PDO;

class Connection
{
//  数据库连接实例
    protected static $instance = array();
    // 查询次数
    protected static $queryTimes = 0;
    // 执行次数
    protected static $executeTimes = 0;
    //执行数据库
    protected static $dbConfig;
    //实例化编码
    protected static $instanceSn;
    //链接信息
    protected $link;
    //执行sql
    protected $queryStr;
    // PDO连接参数
    protected $params = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ];

    /**
     * 数据库初始化 并取得数据库类实例
     * @static
     * @access public
     * @param mixed         $config 连接配置
     * @param bool|string   $name 连接标识 true 强制重新连接
     * @return Connection
     * @throws Exception
     */
    private function __construct($config = array(), $name = false)
    {

        $class = ucwords($config['db_type']);
        $dsn   = Mysqli::parseDsn($config);

        $this->link = $db = new PDO($dsn, $config['db_user'], $config['db_pwd']);
    }

    //单例实例化 避免重复New暂用资源
    public static function getInstance($config = '')
    {
        if (!$config) {
            if (getConfig('db.' . APP_CONFIG)) {
                $config = getConfig('db.' . APP_CONFIG);
            } else {
                $config = getConfig('db');
            }
        }

        self::$dbConfig = $config;

        $name = md5($config);

        if (!isset(self::$instance[$name])) {
            self::$instance[$name] = new Connection($config);
        }

        return self::$instance[$name];

    }

    public function getConfig($value)
    {
        return self::$dbConfig[$value];
    }

    public static function clear()
    {
        self::$instance = null;
    }

    /**
     * 执行
     * @date   2017-03-19T16:20:36+0800
     * @author ChenMingjiang
     * @param  [type]                   $sql [description]
     * @return [type]                        [description]
     */
    public function query($query = '')
    {
        !$query ?: $this->queryStr = $query;
        $_beginTime                = microtime(true);
        $result                    = $this->link->query($this->queryStr);
        $_endTime                  = microtime(true);

        $result_arr = $result->fetchAll();
        print_r($result_arr);
        var_dump($result);

        $this->queryInfo['time'] = $_endTime - $_beginTime; //获取执行时间
        $this->queryInfo['sql']  = $this->_sql;

        if ($result) {
            Trace::addSqlInfo($this->queryInfo); //存入调试信息中
            $this->addSqlLog(); //存入文件中
            return $result;
        } else {
            Trace::addErrorInfo('[SQL ERROR] ' . $this->_sql);
            $this->addErrorSqlLog(); //存入文件
            return false;
        }

    }

    /**
     * DSN解析
     * 格式： mysql://username:passwd@localhost:3306/DbName?param1=val1&param2=val2#utf8
     * @static
     * @access private
     * @param string $dsnStr
     * @return array
     */
    private static function parseDsn($dsnStr)
    {
        $info = parse_url($dsnStr);
        if (!$info) {
            return [];
        }
        $dsn = [
            'type'     => $info['scheme'],
            'username' => isset($info['user']) ? $info['user'] : '',
            'password' => isset($info['pass']) ? $info['pass'] : '',
            'hostname' => isset($info['host']) ? $info['host'] : '',
            'hostport' => isset($info['port']) ? $info['port'] : '',
            'database' => !empty($info['path']) ? ltrim($info['path'], '/') : '',
            'charset'  => isset($info['fragment']) ? $info['fragment'] : 'utf8',
        ];

        if (isset($info['query'])) {
            parse_str($info['query'], $dsn['params']);
        } else {
            $dsn['params'] = [];
        }
        return $dsn;
    }

    /** 错误sql */
    public function addErrorSqlLog()
    {
        //如果没有写入权限尝试修改权限 如果修改后还是失败 则跳过
        if (isWritable(DATA_PATH)) {
            $path = DATA_PATH . 'sql_log' . DS . $this->dbConfig['db_name'] . DS;
            is_dir($path) ? '' : mkdir($path, 0755, true);
            $path .= 'error_' . date('Y_m_d_H', TIME) . '.text';

            $time = &$this->queryInfo['time'];
            $info = '------ ' . $time . ' | ' . date('Y-m-d H:i:s', TIME) . ' | ip:' . getIP() . ' | ';
            $info .= 'Url:' . URL . ' | Controller:' . CONTROLLER . ' | Action:' . ACTION . PHP_EOL;

            $content = $this->queryInfo['sql'] . ';' . PHP_EOL . '来源：' . getSystem() . getBrowser() . PHP_EOL . '--------------' . PHP_EOL;
            $file    = fopen($path, 'a');
            fwrite($file, $content . $info . PHP_EOL);
            fclose($file);
        }
    }

    /**
     * 保存sql记录
     * @date   2017-10-18T13:45:16+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function addSqlLog()
    {
        //如果没有写入权限尝试修改权限 如果修改后还是失败 则跳过
        if (!isWritable(DATA_PATH)) {
            return false;
        }

        //创建文件夹
        is_dir(DATA_PATH . 'sql_log') ? '' : mkdir(DATA_PATH . 'sql_log', 0755, true);

        $time = &$this->queryInfo['time'];
        $info = '------ ' . $time . ' | ' . date('Y-m-d H:i:s', TIME) . ' | ip:' . getIP() . ' | ';
        $info .= 'Url:' . URL . ' | Controller:' . CONTROLLER . ' | Action:' . ACTION . PHP_EOL;

        //记录sql
        if ($this->queryInfo && $this->dbConfig['db_save_log']) {
            $path = DATA_PATH . 'sql_log' . DS . $this->dbConfig['db_name'] . DS;
            is_dir($path) ? '' : mkdir($path, 0755, true);
            if (stripos($this->queryInfo['sql'], 'select') === 0) {
                $path .= 'select_' . date('Y_m_d_H', TIME) . '.text';
                $content = $this->queryInfo['sql'] . PHP_EOL;
            } elseif (stripos($this->queryInfo['sql'], 'update') === 0) {
                $path .= 'update_' . date('Y_m_d_H', TIME) . '.text';
                $content = $this->queryInfo['sql'] . ';' . PHP_EOL;
            } elseif (stripos($this->queryInfo['sql'], 'delete') === 0) {
                $path .= 'delete_' . date('Y_m_d_H', TIME) . '.text';
                $content = $this->queryInfo['sql'] . ';' . PHP_EOL;
            } elseif (stripos($this->queryInfo['sql'], 'insert') === 0) {
                $path .= 'add_' . date('Y_m_d_H', TIME) . '.text';
                $content = $this->queryInfo['sql'] . ';' . PHP_EOL;
            }

            //记录慢sql
            if ($this->dbConfig['db_slow_save_log']) {
                if ($this->queryInfo['time'] > $this->dbConfig['db_slow_time']) {
                    $path .= 'slow_' . date('Y_m_d_H', TIME) . '.text';
                    $content = $this->queryInfo['sql'] . PHP_EOL;
                }
            }

            $file = fopen($path, 'a');
            fwrite($file, $content . $info . PHP_EOL);
            fclose($file);
        }
    }
}
