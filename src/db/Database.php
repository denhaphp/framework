<?php
//------------------------
//· 数据库操作类
//---------------------
namespace denha\db;

use denha;
use denha\Config;
use denha\Log;
use denha\Trace;
use \Exception;
use \PDO;

class Database
{
    private static $configs;
    private static $instance; // 单例实例化;
    private static $do; // 数据库操作符

    public $link; // 当前链接符
    public $id; // 当前链接配置ID

    public $options; // 记录参数信息$
    public $bulid; // 记录构造Sql;

    /** @var [power] [读写类型权限] */
    private $power = [
        'read'  => ['SELECT', 'COUNT'],
        'write' => ['INSERT', 'UPDATE', 'DELETE'],
    ];

    private $config; //

    private function __construct(array $dbConfig = [])
    {
        $this->setConfigs($dbConfig);

    }

    // 单例实例化 避免重复New占用资源
    public static function getInstance(array $config = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new Database($config);
        }

        return self::$instance;

    }

    public function getConfig()
    {
        return $this->config;
    }

    /** 数据库配置读取 */
    public function setConfig(array $config)
    {
        $readWritePower = $config['read_write_power'] ?? 0;
        $hash           = md5(json_encode($config));
        $config['dns']  = $this->parseDNS($config);

        if ($readWritePower == 0) {
            self::$do[1][$hash] = $config;
            self::$do[2][$hash] = $config;
        } else {
            self::$do[$readWritePower][$hash] = $config;
        }

        return $this;
    }

    public function setConfigs(array $dbConfig = [])
    {
        if ($dbConfig) {
            $this->setConfig($item);
        }

        if (!self::$configs) {
            self::$configs = Config::includes(Config::get('db_file'))['config'];
        }

        foreach (self::$configs as $item) {
            $this->setConfig($item);
        }

    }

    public function parseDNS(array $config): string
    {
        switch ($config['type']) {
            case 'mysql':
            case 'mysqli':
                $dns = 'mysql:host=' . $config['host'] . ':' . $config['port'] . ';dbname=' . $config['name'] . ';charset=' . $config['charset'];
                break;
            case 'sqlite':
                $dns = 'sqlite:' . $config['name'];
                break;
            default:
                # code...
                break;
        }

        return $dns;
    }

    /** 打开数据库链接 */
    public function open(array $config)
    {

        $config['user'] = isset($config['user']) ? $config['user'] : '';
        $config['pwd']  = isset($config['pwd']) ? $config['pwd'] : '';

        try {
            $do = new PDO($this->parseDNS($config), $config['user'], $config['pwd']);
        } catch (\PDOException $e) {
            $msg = $e->getMessage();
            if (Config::get('debug')) {
                foreach ($config as $key => $value) {
                    $msg .= '[' . $key . ':' . $value . '] ';
                }
            }
            throw new Exception($msg);
        }

        // $do->exec('set interactive_timeout=24*3600,wait_timeout=24*3600');
        $do->exec('SET sql_mode =\'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION\'');
        $do->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true); // true:客户端查询缓存 false:服务器查询缓存
        $do->setAttribute(PDO::ATTR_PERSISTENT, true); // 持久化连接

        return $do;

    }

    /** 获取最后执行SQL */
    public function getLastSql(): string
    {

        return $this->sqlInfo['sql'];
    }

    /** 链接 */
    public function connect()
    {

        // 根据Sql类型选择链接数据库
        in_array($this->options['type'], $this->power['write']) ? $this->connectWrite() : $this->connectRead();

        if (!$this->link->getAttribute(PDO::ATTR_SERVER_INFO)) {
            throw new Exception('link infomation abnor');
        }

        return $this;
    }

    /** 写数据库连接 */
    public function connectWrite()
    {
        if (!self::$do[1]) {
            throw new Exception('error: database confg not find write power config,plase set read_write_power either 0 or 1 also add new config');
        }

        // 判断是否存在
        if (!isset($this->config['write'])) {
            $this->config['write'] = self::$do[1][array_rand(self::$do[1])];
            // 判断是否存在PDO
            if (!isset($this->config['write']['pdo'])) {
                $this->config['write']['pdo'] = $this->open($this->config['write']);
            }
        }

        $this->link = $this->config['write']['pdo'];

        $this->options['tablepre'] = $this->config['write']['prefix'];
        $this->options['database'] = $this->config['write']['name'];

    }

    /** 读数据库连接 */
    public function connectRead()
    {
        if (!self::$do[1]) {
            throw new Exception('error: database confg not find read power config,plase set read_write_power either 0 or 2 also add new config');
        }

        // 判断是否存在
        if (!isset($this->config['read'])) {
            $this->config['read'] = self::$do[2][array_rand(self::$do[2])];
            // 判断是否存在PDO
            if (!isset($this->config['read']['pdo'])) {
                $this->config['read']['pdo'] = $this->open($this->config['read']);
            }
        }

        $this->link = $this->config['read']['pdo'];

        $this->options['tablepre'] = $this->config['read']['prefix'];
        $this->options['database'] = $this->config['read']['name'];

    }

    /** 构造Sql初始化 */
    public function init()
    {

        $this->bulid = [
            'field'     => '*',
            'sql'       => '',
            'chilidSql' => false,
            'data'      => null,
            'order'     => null,
            'group'     => null,
            'table'     => null,
        ];

        $this->options = [
            'type'  => '',
            'data'  => [],
            'field' => [],
            'order' => [],
            'limit' => [],
            'table' => [],
        ];

    }

    /**
     * 子查询 如果开启 则直接返回sql
     * @date   2017-11-22T00:38:42+0800
     * @author ChenMingjiang
     * @param  boolean                  $value [true：开启子查询 false:关闭子查询]
     * @return [type]                          [description]
     */
    public function childSql(bool $bool = false)
    {
        $this->bulid['chilidSql'] = $bool;

        return $this;
    }

    /**
     * 数据表
     * @date   2018-07-12T11:08:29+0800
     * @author ChenMingjiang
     * @param  [type]                   $table   [description]
     * @param  array                    $options [description]
     *                                  is_tablepre：是否使用表前缀
     *                                  link：链接数据库配置ID
     * @return [type]                            [description]
     */
    public static function table(string $table, array $options = [])
    {

        self::getInstance(); // 实例化

        $isPrefix = $options['is_prefix'] ?? true;
        $link     = $options['link'] ?? '';

        self::$instance->init();
        self::$instance->options['table'] = ['name' => $table, 'is_prefix' => $isPrefix];

        return self::$instance;
    }

    /**
     * 获取表名称
     * @date   2017-06-10T22:56:01+0800
     * @author ChenMingjiang
     * @param  [type]                   $table [description]
     * @return [type]                          [description]
     */
    public function getTableName(): string
    {
        // 链接数据库
        $this->connect();
        $this->parseTable();

        return $this->bulid['table'];
    }

    /**
     * 字段信息增加`标记
     * @date   2018-07-12T11:10:38+0800
     * @author ChenMingjiang
     * @param  [type]                   $field [description]
     */
    private function addFieldTag(string $field): string
    {
        if (
            stripos($field, '`') === false
            && stripos($field, ' ') === false
            && stripos($field, '>') === false
            && stripos($field, '=') === false
            && stripos($field, '<') === false
            && stripos($field, '_STRING') === false
            && stripos($field, '*') === false
            && stripos($field, 'CONCAT') === false
            && stripos($field, 'SUM') === false
            && stripos($field, 'AVG') === false
            && stripos($field, 'AVG') === false
            && stripos($field, 'AS') === false
            && stripos($field, 'DISTINCT') === false
            && stripos($field, 'FIELD') === false
        ) {
            $field = stripos($field, '.') !== false ? str_replace('.', '.`', $field) . '`' : '`' . $field . '`';
        }

        return $field;
    }

    /**
     * 查询条件
     * @date   2019-09-29T16:49:48+0800
     * @author ChenMingjiang
     * @param  [type]                   $where   [description]
     * @param  [type]                   $value   [description]
     * @param  [type]                   $exp     [description]
     * @param  string                   $mapLink [链接符 AND OR]
     * @return [type]                   [description]
     */
    public function where($where, $value = null, $exp = null, $mapLink = 'AND')
    {

        if (!$where) {
            return $this;
        }

        // 存在三个参数 $exp => 参数值
        if ($value !== null && $exp !== null) {
            $map[$where] = [$value, $exp];
            $where       = $map;

        }
        // 存在两个参数
        elseif ($value !== null && $exp === null) {
            if (is_array($value)) {
                throw new Exception("SQL Where 参数值错误 {$where} = `数组`");
            }

            $map[$where] = $value;
            $where       = $map;
        }

        // 批量处理数组
        if (is_array($where)) {
            foreach ($where as $mapField => $v) {
                // 初始化连接符
                $mapLink = 'AND';

                // 数组3个参数
                if (is_array($v) && count($v) == 2) {
                    list($mapExp, $mapValue) = $v;
                }
                // 数组3个参数
                elseif (is_array($v) && count($v) == 3) {
                    list($mapExp, $mapValue, $mapLink) = $v;
                } elseif ($mapField == '_string') {
                    $mapExp   = '_string';
                    $mapValue = $v;
                } else {
                    $mapExp   = '=';
                    $mapValue = $v;
                }

                // 解析map
                $this->parseMap($mapField, $mapExp, $mapValue, $mapLink);
            }

        }
        // 单条where语句
        else {
            //记录条件参数
            $this->options['map'][] = [$where, $mapLink];
        }

        return $this;
    }

    /**
     * 关联查询
     * @date   2018-07-12T11:12:45+0800
     * @author ChenMingjiang
     * @param  [type]                   $table   [description]
     * @param  string                   $where   [description]
     * @param  string                   $float   [description]
     * @param  array                    $options [description]
     * @return [type]                            [description]
     */
    public function join(string $table, $where = '', $float = 'left')
    {

        $this->options['join'][] = ['table' => $table, 'where' => $where, 'float' => $float];

        return $this;
    }

    /**
     * 查询数量
     * @date   2017-03-19T16:18:13+0800
     * @author ChenMingjiang
     * @param  [type]                   $limit [description]
     * @return [type]                          [description]
     */
    public function limit(int $limit = 0, $pageSize = '')
    {

        $this->options['limit'] = ['offset' => $limit, 'pageSize' => $pageSize];

        return $this;
    }

    /** 查询字段 */
    public function field($field = '*')
    {

        if (!$field) {
            return $this;
        }

        $this->options['field'] = is_array($field) ? $field : explode(',', $field);

        return $this;
    }

    /** 编辑字段 */
    public function setData($data, $exp = null, $value = null)
    {
        if ($exp !== null && $value === null) {
            $data = [$data => $exp];
        } elseif ($value !== null) {
            $data = [$data => [$exp, $value]];
        }

        $data = is_array($data) ? $data : explode(',', $data);

        $this->options['data'] = $data;

        return $this;
    }

    /** 分组 */
    public function group($value = '')
    {

        if (!$value) {
            return $this;
        }

        $this->options['group'] = is_array($value) ? $value : explode(',', $value);

        return $this;
    }

    /**
     *
     * 排序
     * @date   2018-12-19T10:53:46+0800
     * @author ChenMingjiang
     * @param  [type]                   $value [description]
     * @return [type]                   [description]
     */
    public function order($field)
    {
        if (!$field) {
            return $this;
        }

        // 分割逗号
        if (!is_array($field) && stripos($field, '(') === false) {
            $fields = explode(',', $field);
        } elseif (is_array($field)) {
            foreach ($field as $key => $value) {
                $fields[] = $key . ' ' . $value;
            }
        } else {
            $fields[] = $field;
        }

        $this->options['order'][] = $fields;

        return $this;

        // 分割字段 和排序类型
        foreach ($fields as $key => $val) {
            // 包含 () 的关键字
            if (stripos($val, '(') !== false) {
                $parseOrder[$key] = ' ' . $val;
            } else {
                list($field, $exp) = explode(' ', $val);
                $parseOrder[$key]  = ' ' . $this->addFieldTag($field) . ' ' . $exp;
            }
        }

        if ($parseOrder) {
            $this->options['order'] = array_merge($parseOrder, $this->options['order'] ?: []);
        }

        return $this;
    }

    /**
     * hvaing
     * @date   2017-11-22T01:18:55+0800
     * @author ChenMingjiang
     * @param  [type]                   $field [description]
     * @return [type]                          [description]
     */
    public function having($field)
    {
        $this->options['having'] = $field;
        return $this;
    }

    /** 解析表名 */
    public function parseTable()
    {

        if (!$this->options['table']) {
            throw new Exception('plase choose table name');
        }

        // 判断是否存在 as
        if (stripos($this->options['table']['name'], ' as ') !== false) {
            list($name, $as) = explode(' AS ', str_replace(' as ', ' AS ', $this->options['table']['name']));
        } else {
            $name = $this->options['table']['name'];
        }

        $name = parseName(trim($name));
        $as   = isset($as) ? trim($as) : '';

        $name = $this->options['table']['is_prefix'] && $this->options['tablepre'] ? $this->options['tablepre'] . $name : $name;

        if (empty($this->bulid['table'])) {
            $this->bulid['table'] = $as ? $name . ' AS ' . $as : $name;
        }

    }

    /** 处理查询条件 */
    public function parseMap($mapField, $mapExp, $mapValue, $mapLink = 'AND'): array
    {

        $mapField = $this->addFieldTag($mapField); // 格式化字段

        $mapLink = ' ' . $mapLink . ' '; // 连接符
        $expRule = [
            ['>', '<', '>=', '<=', '!=', 'like', '<>', '='],
            ['in', 'not in', 'IN', 'NOT IN'],
            ['instr', 'INSTR', 'not insrt', 'NOT INSTR'],
            ['between', 'BETWEEN'],
            ['or', 'OR'],
            ['_string', '_STRING'],
            ['find_in_set', 'FIND_IN_SET'],
        ];

        // '>', '<', '>=', '<=', '!=', 'like', '<>'
        if (in_array($mapExp, $expRule[0])) {
            $map = $mapField . '  ' . $mapExp . ' \'' . $mapValue . '\'';
        }
        // 'in', 'not in', 'IN', 'NOT IN'
        elseif (in_array($mapExp, $expRule[1])) {
            if (!$mapValue) {
                $map = $mapField . '  ' . $mapExp . ' (\'\')';
            } else {
                if (!is_array($mapValue) && stripos($mapValue, ',') !== false) {
                    $mapValue = explode(',', $mapValue);
                }
                $mapValue      = is_array($mapValue) ? $mapValue : (array) $mapValue;
                $commonInValue = '';
                foreach ($mapValue as $inValue) {
                    $commonInValue .= '\'' . $inValue . '\',';
                }
                $commonInValue = substr($commonInValue, 0, -1);
                $map           = $mapField . '  ' . $mapExp . ' (' . $commonInValue . ')';
            }
        }
        // 'instr', 'INSTR'
        elseif (in_array($mapExp, $expRule[2])) {
            $map = $mapExp . '(' . $mapField . ',\'' . $mapValue . '\')';
        }
        // 'between', 'BETWEEN'
        elseif (in_array($mapExp, $expRule[3])) {
            $map = $mapField . '  ' . $mapExp . ' \'' . $mapValue[0] . '\' AND \'' . $mapValue[1] . '\'';
        }
        // 'or', 'OR'
        elseif (in_array($mapExp, $expRule[4])) {
            if (count((array) $mapValue) == 2) {
                return $this->parseMap($mapField, $mapValue[0], $mapValue[1], strtoupper($mapExp));
            } else {
                $mapLink = ' ' . strtoupper($mapExp) . ' '; // 连接符
                $map     = $mapField . ' = \'' . $mapValue . '\'';
            }
        }
        // '_string', '_STRING'
        elseif (in_array($mapExp, $expRule[5])) {
            $map = $mapValue;
        }
        // 'find_in_set', 'FIND_IN_SET'
        elseif (in_array($mapExp, $expRule[6])) {
            $mapValue = explode(',', $mapValue);
            $mapArs   = [];
            foreach ($mapValue as $value) {
                $mapArs[] = $map = $mapExp . '(' . $value . ', ' . $mapField . ')';

                $this->options['map'][] = [$map, $mapLink];
            }

            return [implode($mapLink, $mapArs), $mapLink];
        } else {
            throw new Exception('SQL WHERE 参数错误 `' . $mapExp . '`');
        }

        $this->options['map'][] = [$map, $mapLink];

        return [$map, $mapLink];
    }

    /** 解析查询条件 */
    public function parseWhere(array $whereMap = []): string
    {
        $whereMap = $whereMap ? $whereMap : $this->options['map'];

        if (count($whereMap) > 1) {
            // 第一个OR出现
            $orStart = true;
            foreach ($whereMap as $kk => $vv) {
                if (trim($vv[1]) == 'OR' && $orStart) {
                    $orStart = false;
                    // 存在上一条信息 则上一条增加一个 "("
                    if ($kk > 0) {
                        $whereMap[($kk - 1)][1] = $whereMap[($kk - 1)][1] . ' ( ';
                    }
                    // 否则当前信息增加一个 "("
                    else {
                        $whereMap[$kk][1] = $whereMap[$kk][1] . ' ( ';
                    }
                }

                // 最后一个OR出现
                if (trim($vv[1]) == 'AND' && !$orStart) {
                    $orStart          = true;
                    $whereMap[$kk][0] = $whereMap[$kk][0] . ' ) ';
                }
                // 最后一条数据
                elseif (!$orStart && count($whereMap) == ($kk + 1)) {
                    $orStart          = true;
                    $whereMap[$kk][0] = $whereMap[$kk][0] . ' ) ';
                }

            }
        }

        $mapSql = ' WHERE ';
        foreach ($whereMap as $key => $item) {

            list($field, $link) = $item;
            if (count($whereMap) - 1 == $key) {

                $mapSql .= $field;
            } else {
                $mapSql .= $field . $link;
            }
        }

        return $mapSql;
    }

    /** 解析Join */
    public function parseJoin()
    {
        foreach ($this->options['join'] as $item) {

            if ($this->bulid['table'] && empty($item['where'])) {
                $item['where'] = $this->bulid['table'] . '.id = ' . $item['table'] . '.id';
            }

            if (!isset($this->bulid['join'])) {
                $this->bulid['join'] = ' ' . $item['float'] . ' JOIN ' . $item['table'] . ' ON ' . $item['where'];
            } else {
                $this->bulid['join'] .= ' ' . $item['float'] . ' JOIN ' . $item['table'] . ' ON ' . $item['where'];
            }
        }

        return $this->bulid['join'];

    }

    /** 解析limit */
    public function parseLimit()
    {

        $this->bulid['limit'] = ' LIMIT ' . $this->options['limit']['offset'];
        if ($this->options['limit']['pageSize']) {
            $this->bulid['limit'] = ' LIMIT ' . $this->options['limit']['offset'] . ',' . $this->options['limit']['pageSize'];
        }

        return $this->bulid['limit'];
    }

    /** 解析field */
    public function parseField()
    {
        if (!$this->options['field']) {
            $this->bulid['field'] = '*';
        } else {
            $this->bulid['field'] = '';
            foreach ($this->options['field'] as $val) {
                $this->bulid['field'] .= $this->addFieldTag($val) . ',';
            }

            $this->bulid['field'] = substr($this->bulid['field'], 0, -1);
        }

        return $this->bulid['field'];
    }

    /** 解析save */
    public function parseSetData()
    {
        $this->bulid['data'] = '';

        foreach ($this->options['data'] as $k => $v) {
            $k = $this->addFieldTag($k);
            if (is_array($v)) {

                $v[0] = strtolower($v[0]);

                if ($v[0] == 'add') {
                    $this->bulid['data'] .= $k . ' = ' . $k . ' + ' . $v[1] . ',';
                } elseif ($v[0] == 'less') {
                    $this->bulid['data'] .= $k . ' = ' . $k . ' - ' . $v[1] . ',';
                } elseif ($v[0] == 'concat') {
                    $this->bulid['data'] .= $k . ' = CONCAT(' . $k . ',\'\',\'' . str_replace('\'', '\\\'', $v[1]) . '\'),';
                } elseif ($v[0] == 'json') {
                    $this->bulid['data'] .= $k . ' = \'' . json_encode($v[1], JSON_UNESCAPED_UNICODE) . '\',';
                }
            } else {
                $v = str_replace('\\', '\\\\', $v);
                $v = str_replace('\'', '\\\'', $v);

                $this->bulid['data'] .= $k . ' = \'' . $v . '\',';
            }

        }

        $this->bulid['data'] = substr($this->bulid['data'], 0, -1);

        return $this->bulid['data'];
    }

    public function parseGroup()
    {
        foreach ($this->options['group'] as $val) {
            $this->bulid['group'] .= $this->addFieldTag($val) . ',';
        }

        if (!$this->bulid['order']) {
            $this->bulid['group'] = ' GROUP BY ' . substr($this->bulid['group'], 0, -1);
        } else {
            $this->bulid['group'] = ' , ' . substr($this->bulid['group'], 0, -1);
        }

        return $this->bulid['group'];
    }

    public function parseOrder()
    {

        foreach ($this->options['order'] as $fields) {
            // 分割字段 和排序类型
            foreach ($fields as $key => $val) {
                // 包含 () 的关键字
                if (stripos($val, '(') !== false) {
                    $orders[$key] = ' ' . $val;
                } else {
                    list($field, $exp) = explode(' ', $val);
                    $orders[$key]      = ' ' . $this->addFieldTag($field) . ' ' . $exp;
                }
            }

            if (!$this->bulid['order']) {
                $this->bulid['order'] = ' ORDER BY ' . implode(',', $orders);
            } else {
                $this->bulid['order'] .= ' , ' . implode(',', $orders);
            }
        }

        return $this->bulid['order'];
    }

    public function parseHaving()
    {
        $this->bulid['having'] = ' HAVING ' . $this->addFieldTag($field);

        return $this->bulid['order'];
    }

    /**
     * 子查询 如果开启 则直接返回sql
     * @date   2017-11-22T00:38:42+0800
     * @author ChenMingjiang
     * @param  boolean                  $value [description]
     * @return [type]                          [description]
     */
    public function getSql(bool $bool = true)
    {
        $this->options['childSql'] = $bool;

        if ($bool) {
            $this->bulidSql('SELECT');
            return $this->bulid['sql'];
        }

        return $this;
    }

    /**
     * 子查询table
     * @date   2017-11-22T00:45:38+0800
     * @author ChenMingjiang
     * @param  [type]                   $table [description]
     * @return [type]                          [description]
     */
    public function childSqlQuery(string $table)
    {
        $this->bulid['table'] = '(' . $table . ') as child';

        return $this;
    }

    /** 构建SQL语句 */
    public function bulidSql(string $type = 'SELECT')
    {

        $this->options['type'] = $type;

        $this->connect(); // 链接数据库

        $this->parseTable(); // 解析数据表

        $this->parseField(); // 解析字段信息

        switch ($type) {
            case 'SELECT':
                $this->bulid['sql'] = 'SELECT ' . $this->bulid['field'] . ' FROM ' . $this->bulid['table'];
                break;
            case 'UPDATE':
                $this->bulid['sql'] = 'UPDATE ' . $this->bulid['table'] . ' SET ' . $this->parseSetData();
                break;
            case 'INSERT':
                $this->bulid['sql'] = 'INSERT INTO ' . $this->bulid['table'] . ' SET ' . $this->parseSetData();
                break;
            case 'DELETE':
                $this->bulid['sql'] = 'DELETE FROM ' . $this->bulid['table'];
                break;
            case 'COUNT':
                $this->bulid['sql'] = 'SELECT  COUNT(' . $this->bulid['field'] . ') AS  t  FROM ' . $this->bulid['table'];
                break;
            default:
                # code...
                break;
        }

        if (isset($this->options['join']) && !empty($this->options['join'])) {
            $this->bulid['sql'] .= $this->parseJoin();
        }

        if (isset($this->options['map']) && !empty($this->options['map'])) {
            $this->bulid['sql'] .= $this->parseWhere();
        }

        if (isset($this->options['group']) && !empty($this->options['group'])) {
            $this->bulid['sql'] .= $this->parseGroup();
        }

        if (isset($this->options['having']) && !empty($this->options['having'])) {
            $this->bulid['sql'] .= $this->parseHaving();
        }

        if (isset($this->options['order']) && !empty($this->options['order'])) {
            $this->bulid['sql'] .= $this->parseOrder();
        }

        if (isset($this->options['limit']) && !empty($this->options['limit'])) {
            $this->bulid['sql'] .= $this->parseLimit();
        }

    }

    /** 查询数据表信息 */
    public function getTableStatus($field = '')
    {

        $this->connect();
        $this->parseTable();

        $this->field($field);
        $this->parseField();

        $this->bulid['sql'] = 'SHOW TABLE STATUS WHERE NAME = \'' . $this->bulid['table'] . '\'';
        $result             = $this->query();
        $lists              = $result->fetch(PDO::FETCH_ASSOC);

        if (count($this->options['field']) == 1 && $lists) {
            foreach ($lists as $key => $value) {
                if (!isset($lists[$field])) {
                    throw new Exception('SQL ERROR : 字段信息不存在 [' . $field . ']');
                }

                $data = $lists[$field];
            }
        } else {
            $data = $lists;
        }

        return $data;
    }

    /** 查询表字段名 */
    public function getField($field = 'COLUMN_NAME')
    {
        $this->connect();
        $this->parseTable();

        $this->field($field);
        $this->parseField();

        $where = ' WHERE table_name = \'' . $this->bulid['table'] . '\'';
        if ($this->options['group']) {
            $where .= $this->parseGroup();
        }

        $this->bulid['sql'] = 'SELECT ' . $this->bulid['field'] . ' from information_schema.columns ' . $where;
        $result             = $this->query();
        $list               = $result->fetchAll(PDO::FETCH_ASSOC);

        if (count($this->options['field']) == 1) {
            foreach ($list as $key => $value) {
                $data[] = $value[$field];
            }
        } else {
            $data = $list;
        }

        return $data;
    }

    /**
     * 获取单个字段内容
     * @date   2018-04-06T21:35:17+0800
     * @author ChenMingjiang
     * @param  [type]                   $field [description]
     * @return [type]                          [description]
     */
    public function value(string $field = '')
    {

        if ($field) {
            $this->field($field);
        }

        $this->limit(1);
        $this->bulidSql('SELECT');

        $result = $this->query();

        if ($result) {
            $data = $result->fetchColumn();
        }

        return $data ?: '';

    }

    /**
     * 获取单个字段列表
     * 如果field是两个字段 则首字段为value 尾字段为key
     * @date   2018-07-12T14:41:02+0800
     * @author ChenMingjiang
     * @param  [type]                   $field [description]
     * @return [type]                          [description]
     */
    public function column($field = '')
    {

        if ($field) {
            $this->field($field);
        }

        $this->bulidSql('SELECT');

        $result = $this->query();

        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            if (count($this->options['field']) == 2) {
                $data[$row[1]] = $row[0];
            } else {
                $data[] = $row[0];
            }
        }

        return $data ?? [];
    }

    /**
     * 单条查询
     * @date   2018-07-13T11:01:05+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function find($field = '')
    {

        if ($field) {
            $this->field($field);
        }

        $this->limit(1);
        $this->bulidSql('SELECT');

        $result = $this->query();

        $data = $result->fetch(PDO::FETCH_ASSOC);

        return $data;

    }

    /** 数组索引 */
    public function lists($field = '*')
    {
        if ($field) {
            $this->field($field);
        }

        $this->limit(1);
        $this->bulidSql('SELECT');

        $result = $this->query();

        $data = $result->fetch(PDO::FETCH_NUM);
        if ($data == false) {
            foreach ($this->options['field'] as $value) {
                $data[$value] = '';
            }
        }

        return $data;
    }

    /**
     * 多条查询
     * @date   2018-07-13T11:01:13+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function select()
    {

        $this->bulidSql('SELECT');

        $result = $this->query();

        $data = $result->fetchAll(PDO::FETCH_ASSOC);

        return $data;
    }

    /**
     * 统计总条数
     * @date   2018-07-13T11:01:24+0800
     * @author ChenMingjiang
     * @param  [type]                   $value [description]
     * @return [type]                          [description]
     */
    public function count($value = null)
    {
        if ($value) {
            $this->field($value);
        }

        $this->bulidSql('COUNT');

        $result = $this->query();
        $data   = $result->fetchColumn();

        return (int) $data;

    }

    /** 添加 */
    public function add($data = '', $exp = null, $value = null)
    {

        $this->setData($data, $exp, $value);

        $this->bulidSql('INSERT');
        $result = $this->query();
        $id     = $this->link->lastInsertId();

        return $id;
    }

    /**
     * 添加多条信息
     * @date   2017-09-19T15:45:40+0800
     * @author ChenMingjiang
     */
    public function addAll($data = [])
    {

        foreach ($data as $item) {
            $result = $this->add($item);
        }

        return $result;
    }

    /** 修改保存 */
    public function save($data = '', $exp = null, $value = null)
    {
        $this->setData($data, $exp, $value);

        $this->bulidSql('UPDATE');
        $result = $this->query();
        $num    = $result->rowCount();

        return $num;
    }

    /** 删除 */
    public function delete()
    {

        if (empty($this->options['map'])) {
            throw new Exception('SQL ERROR : 禁止全表删除');
        }

        $this->bulidSql('DELETE');
        $result = $this->query();
        $num    = $result->rowCount();

        return $num;
    }

    // 开启事务
    public function startTrans()
    {
        // 链接数据库
        $this->connect();
        $this->link->beginTransaction();
        return true;
    }

    // 回滚事务
    public function rollback()
    {
        // 链接数据库
        $this->connect();
        $this->link->rollBack();
        return true;
    }

    // 提交事务
    public function commit()
    {
        // 链接数据库
        $this->connect();
        $this->link->commit();
        return true;
    }

    /**
     * 执行
     * @date   2017-03-19T16:20:36+0800
     * @author ChenMingjiang
     * @param  [type]                   $sql [description]
     * @return [type]                        [description]
     */
    public function query($sql = '')
    {

        // 链接数据库
        $this->connect();

        $this->bulid['sql'] = $sql ? $sql : $this->bulid['sql'];

        // 存入Sql Explain信息
        if (!empty($this->config['sql_explain'])) {
            $res = $this->link->query('explain ' . $this->bulid['sql']);
            if ($res) {
                $this->sqlInfo['explain'] = $res->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $_beginTime = microtime(true);
        $result     = $this->link->query($this->bulid['sql']);
        $_endTime   = microtime(true);

        $this->sqlInfo['time'] = $_endTime - $_beginTime; // 获取执行时间
        $this->sqlInfo['sql']  = $this->bulid['sql']; // 执行Sql

        // 日志记录埋点
        // Log::info('SQL:' . $this->bulid['sql'] . ' [' . $this->sqlInfo['time'] . 's]');
        // 调试模式
        // if (Config::get('trace')) {
        Trace::addSql($this->sqlInfo);
        // }

        // 执行成功
        if ($result) {

            return $result;
        } else {

            list($errorCode, $errorNumber, $errorMsg) = $this->link->errorInfo();
            // 存入文件
            if (Config::get('debug') || $this->config['error_log']) {
                Log::error('SQL ERROR :' . $errorCode . ' ' . $errorMsg . ' SQL :  ' . $this->bulid['sql']);
            }

            if (Config::get('debug')) {
                throw new Exception('SQL ERROR :' . $errorCode . ' ' . $errorMsg . ' SQL :  ' . $this->bulid['sql'] . PHP_EOL);
            }

            return false;

        }

    }
}
