<?php
//------------------------
//· 数据库操作类
//---------------------
namespace denha\db;

use denha;
use denha\Cache;
use denha\Config;
use denha\Log;
use denha\Trace;
use \Exception;
use \PDO;

abstract class Container
{

    private static $do; // 数据库操作符

    public $options; // 记录参数信息
    public $bulid; // 记录构信息;

    public $transactions = 0; // 事务计数器 处理事务嵌套问题

    /** @var [power] [读写类型权限] */
    private $power = [
        'read'  => ['SELECT', 'COUNT'],
        'write' => ['INSERT', 'UPDATE', 'DELETE'],
    ];

    private $configs; // 整个数据库配置信息
    private $config; // 当前使用的读写配置信息
    private $PDOStatement; // PDO执行实例

    public $link; // 当前链接符
    public $id; // 当前链接配置信息

    /** 字段类型 */
    const FIELD_PARAM = [
        'bool' => PDO::PARAM_BOOL,
        'null' => PDO::PARAM_NULL,
        'int'  => PDO::PARAM_INT,
        'str'  => PDO::PARAM_STR,
        'lob'  => PDO::PARAM_LOB,
    ];

    public function __construct(array $dbConfig = [])
    {
        $this->setConfigs($dbConfig);

    }

    /** 解析Dsn */
    abstract protected function parseDsn(array $config);

    /** SQL调试信息 */
    abstract protected function explain();

    /** 查询表字段名 */
    abstract public function getField($field);

    public function getConfig()
    {
        return $this->config;
    }

    public function getConfigs()
    {
        return $this->configs;
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getBulid()
    {
        return $this->bulid;
    }

    /** 数据库配置读取 */
    public function setConfig(array $config)
    {
        $type = [0 => 'all', 1 => 'write', 2 => 'read'];

        $power = $type[($config['read_write_power'] ?? 0)];
        $hash  = md5(json_encode($config));

        $config['dns'] = $this->parseDsn($config);

        self::$do[$power][$hash] = $config;

        return $this;
    }

    public function setConfigs(array $dbConfig = [])
    {
        if ($dbConfig) {
            $this->configs = $dbConfig;
        }

        foreach ($this->configs as $item) {
            $this->setConfig($item);
        }

    }

    /** 打开数据库链接 */
    public function open(array $config)
    {

        $config['user'] = isset($config['user']) ? $config['user'] : '';
        $config['pwd']  = isset($config['pwd']) ? $config['pwd'] : '';

        try {
            $do = new PDO($this->parseDsn($config), $config['user'], $config['pwd']);
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

        return $this->getRealSql($this->bulid['sql'], $this->bulid['params']);
    }

    /** 获取程序最后执行id */
    public function getLastInsertId()
    {
        return $this->link->lastInsertId();
    }

    /**
     * 链接
     * @date   2020-02-15T17:17:05+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function connect()
    {
        // 分离读写操作->只有存在读数据库才会执行读写分离
        if (!in_array($this->options['type'], $this->power['write']) && isset(self::$do['read'])) {
            $this->connectRead();
        } else {
            $this->connectWrite();
        }

        if (!$this->link->getAttribute(PDO::ATTR_SERVER_INFO)) {
            throw new Exception('SQL Service Link Infomation Abnor');
        }

        return $this;
    }

    /** 写数据库连接 */
    public function connectWrite()
    {

        if (!isset(self::$do['all']) && !isset(self::$do['write'])) {
            throw new Exception('error: database confg not find write power config,plase set read_write_power either 0 or 1 also add new config');
        }

        // 判断是否存在
        if (!isset($this->config['write'])) {
            if (isset(self::$do['write'])) {
                $this->config['write'] = self::$do['write'][array_rand(self::$do['write'])];
            } else {
                $this->config['write'] = self::$do['all'][array_rand(self::$do['all'])];
            }

            // 判断是否存在PDO
            if (!isset($this->config['write']['pdo'])) {
                $this->config['write']['pdo'] = $this->open($this->config['write']);
            }
        }

        // 断线重连
        if (!$this->config['write']['pdo']->getAttribute(PDO::ATTR_SERVER_INFO)) {
            $this->config['write']['pdo'] = $this->open($this->config['write']);
        }

        $this->link = $this->config['write']['pdo'];
        $this->id   = $this->config['write'];

        $this->options['tablepre'] = $this->config['write']['prefix'];
        $this->options['database'] = $this->config['write']['name'];

    }

    /** 读数据库连接 */
    public function connectRead()
    {

        if (!isset(self::$do['all']) && !isset(self::$do['read'])) {
            throw new Exception('error: database confg not find write power config,plase set read_write_power either 0 or 1 also add new config');
        }

        // 判断是否存在
        if (!isset($this->config['read'])) {
            if (isset(self::$do['read']) && isset(self::$do['all'])) {
                $thisDo               = array_rand(['all' => 0, 'read' => 2]);
                $this->config['read'] = self::$do[$thisDo][array_rand(self::$do[$thisDo])];
            } elseif (isset(self::$do['read'])) {
                $this->config['read'] = self::$do['all'][array_rand(self::$do['read'])];
            } else {
                $this->config['read'] = self::$do['all'][array_rand(self::$do['all'])];
            }

            // 判断是否存在PDO
            if (!isset($this->config['read']['pdo']) || $this->config['read']['pdo']->getAttribute(PDO::ATTR_SERVER_INFO)) {
                $this->config['read']['pdo'] = $this->open($this->config['read']);
            }
        }

        // 断线重连
        if (!$this->config['read']['pdo']->getAttribute(PDO::ATTR_SERVER_INFO)) {
            $this->config['read']['pdo'] = $this->open($this->config['read']);
        }

        $this->link = $this->config['read']['pdo'];
        $this->id   = $this->config['read'];

        $this->options['tablepre'] = $this->config['read']['prefix'];
        $this->options['database'] = $this->config['read']['name'];

    }

    /** 构造Sql初始化 */
    public function init()
    {

        $this->bulid = [
            'field'    => '*',
            'sql'      => '',
            'childSql' => false,
            'data'     => [],
            'order'    => null,
            'group'    => null,
            'table'    => null,
            'params'   => [],
            'tmp'      => false,
        ];

        $this->options = [
            'type'  => '',
            'data'  => [],
            'field' => [],
            'order' => [],
            'limit' => [],
            'table' => [],
            'cache' => [],
            'map'   => [],
            'tmp'   => false,
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
        $this->bulid['childSql'] = $bool;

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
    public function table(string $table, array $options = [])
    {

        $isPrefix = $options['is_prefix'] ?? true;

        $this->init();
        $this->options['table'] = ['name' => $table, 'is_prefix' => $isPrefix];

        return $this;
    }

    /**
     * 开启临时表
     * @date   2020-04-14T12:00:34+0800
     * @author ChenMingjiang
     * @param  boolean                  $name [重命名表名称]
     * @param  [type]                   $type [表存储类型]
     * @return [type]                   [description]
     */
    public function tmp($name = true, $type = null)
    {

        $this->options['tmp'] = $name;

        return $this;
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
            && stripos($field, '(') === false
            && stripos($field, ')') === false
        ) {
            $field = stripos($field, '.') !== false ? str_replace('.', '.`', $field) . '`' : '`' . $field . '`';
        }

        return $field;
    }

    /**
     * 添加字段数据类型
     * @date   2020-04-10T16:48:45+0800
     * @author ChenMingjiang
     * @param  [type]                   $name   [str:需要绑定类型的字段变量名称 array批量添加]
     * @param  [type]                   $type   [类型选择 int bool str lob]
     * @param  [type]                   $length [长度限制]
     * @return [type]                   [description]
     */
    public function type($name, $type = null)
    {
        if (is_array($name)) {

            foreach ($name as $key => $value) {
                $this->type($key, $value);
            }

            return true;
        }

        if (!isset($this->bulid['params'][$this->addFieldTag($name)])) {
            return false;
        }

        $fields = &$this->bulid['params'][$this->addFieldTag($name)];

        $i    = 0;
        $type = is_array($type) ? array_values($type) : (array) $type;

        foreach ($fields as $key => $item) {

            // 当数组只有一个值的时候 所有参数变量全部统一替换成一个类型
            if (count($type) == 1) {
                $item['type'] = $type[0];
            } elseif (count($type) < $i + 1) {
                $item['type'] = 'str';
            } else {
                $item['type'] = $type[$i];
                $i++;
            }

            if (!isset(self::FIELD_PARAM[$item['type']])) {
                $item['type'] = 'str';
            }

            $fields[$key]['type'] = $item['type'];
        }

        return $this;
    }

    /**
     * 查询条件
     * @date   2019-09-29T16:49:48+0800
     * @author ChenMingjiang
     * @param  [type]                   $where   [字段值 数组【 [field] => ['exp','value','maplink'] 】]
     * @param  [type]                   $exp     [比较类型]
     * @param  [type]                   $value   [值]
     * @param  string                   $mapLink [链接符 AND OR]
     * @return [type]                   [description]
     */
    public function where($where, $exp = null, $value = null, $mapLink = 'AND')
    {

        if (!$where) {
            return $this;
        }

        // 存在三个参数 $exp => 参数值
        if ($value !== null && $exp !== null) {
            $map[$where] = [$exp, $value, $mapLink];
            $where       = $map;

        }
        // 存在两个参数
        elseif ($value === null && $exp !== null) {
            if (is_array($value)) {
                throw new Exception("SQL Where 参数值错误 {$where} = `数组`");
            }

            $map[$where] = ['=', $exp, $mapLink];
            $where       = $map;
        }

        // 批量处理数组
        if (is_array($where)) {
            foreach ($where as $mapField => $v) {

                // 数组2个参数
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

    /**
     * 绑定预加载信息
     * @date   2020-04-11T10:55:36+0800
     * @author ChenMingjiang
     * @param  array                    $bind [description]
     * @return [type]                   [description]
     */
    public function buildParam(array $bind = [])
    {

        list($name, $value, $type) = array_values($bind);

        $type = in_array($type, self::FIELD_PARAM) ? self::FIELD_PARAM[$type] : PDO::PARAM_STR;

        if ($this->PDOStatement) {

            $this->PDOStatement->bindParam($name, $value, $type);
        }

        return [$name, $value, $type];
    }

    /**
     * 绑定预处理参数
     * @date   2020-04-11T09:17:50+0800
     * @author ChenMingjiang
     * @param  [type]                   $field [字符实际名称]
     * @param  [type]                   $name  [绑定变量名称]
     * @param  [type]                   $value [绑定值]
     * @param  string                   $type  [绑定值类型]
     */
    public function setBuildParam($field, $name, $value, $type = 'str')
    {

        $this->bulid['params'][$field][$name] = ['name' => $name, 'value' => $value, 'type' => $type];

    }

    /** 处理查询条件 */
    public function parseMap($mapField, $mapExp, $mapValue, $mapLink = 'AND'): array
    {
        $mapValueField = ':' . str_replace('.', '_', $mapField); // 预加载名称
        $mapField      = $this->addFieldTag($mapField); // 格式化字段
        $bankMapExp    = ' ' . trim($mapExp) . ' '; // 格式条件
        $mapLink       = ' ' . $mapLink . ' '; // 连接符
        $expRule       = [
            ['>', '<', '>=', '<=', '!=', 'like', '<>', '='], // 0
            ['in', 'not in', 'IN', 'NOT IN'], // 1
            ['instr', 'INSTR', 'not insrt', 'NOT INSTR'], // 2
            ['between', 'BETWEEN'], // 3
            ['or', 'OR'], // 4
            ['_string', '_STRING'], // 5
            ['find_in_set', 'FIND_IN_SET'], // 6
            ['locate', 'LOCATE'], // 7
        ];

        // '>', '<', '>=', '<=', '!=', 'like', '<>'
        if (in_array($mapExp, $expRule[0])) {
            // 若field 为id强制转值为整型
            $mapValue = $mapField == '`id`' ? ' ' . (int) $mapValue : $mapValue;
            $map      = $mapField . $bankMapExp . $mapValueField;
        }
        // 'in', 'not in', 'IN', 'NOT IN'
        elseif (in_array($mapExp, $expRule[1])) {

            if (!is_array($mapValue) && stripos($mapValue, ',') !== false) {
                $mapValue = explode(',', $mapValue);
            } elseif (!$mapValue) {
                $mapValue = '';
            }

            $mapValue = is_array($mapValue) ? $mapValue : (array) $mapValue;

            $commonValueField = [];
            foreach ($mapValue as $key => $inValue) {

                $key                = $mapValueField . '_' . $key;
                $commonValueField[] = $key;

                $this->setBuildParam($mapField, $key, $inValue);
            }

            $map = $mapField . $bankMapExp . '(' . implode(',', $commonValueField) . ')';

            $this->options['map'][] = [$map, $mapLink];

            return [$map, $mapLink];

        }
        // 'instr', 'INSTR', 'not insrt', 'NOT INSTR'
        elseif (in_array($mapExp, $expRule[2])) {
            $map = $bankMapExp . '(' . $mapField . ',' . $mapValueField . ')';
        }
        // 'between', 'BETWEEN'
        elseif (in_array($mapExp, $expRule[3])) {
            $map = $mapField . $bankMapExp . $mapValueField . '_start' . ' AND ' . $mapValueField . '_end';

            $this->setBuildParam($mapField, $mapValueField . '_start', $mapValue[0]);
            $this->setBuildParam($mapField, $mapValueField . '_end', $mapValue[1]);

            $this->options['map'][] = [$map, $mapLink];

            return [$map, $mapLink];
        }
        // 'or', 'OR'
        elseif (in_array($mapExp, $expRule[4])) {
            if (count((array) $mapValue) == 2) {
                return $this->parseMap($mapField, $mapValue[0], $mapValue[1], strtoupper($mapExp));
            } else {
                $mapLink = ' ' . strtoupper($mapExp) . ' '; // 连接符
                $map     = $mapField . ' = ' . $mapValueField;
            }
        }
        // '_string', '_STRING'
        elseif (in_array($mapExp, $expRule[5])) {
            $map                    = $mapValue;
            $this->options['map'][] = [$map, $mapLink];
            return [$map, $mapLink];
        }
        // 'find_in_set', 'FIND_IN_SET'
        elseif (in_array($mapExp, $expRule[6])) {
            $mapValue = implode(',', (array) $mapValue);

            $map = $bankMapExp . '(' . $mapValueField . ',' . $mapField . ')';
        }
        // 'locate', 'LOCATE'
        elseif (in_array($mapExp, $expRule[2])) {
            $map = $bankMapExp . '(' . $mapValueField . ',' . $mapField . ')';
        } else {
            throw new Exception('SQL WHERE 参数错误 mapExp:`' . $mapExp . '`');
        }

        $this->options['map'][] = [$map, $mapLink];

        $this->setBuildParam($mapField, $mapValueField, $mapValue);

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

        foreach ($this->options['data'] as $field => $value) {

            if (is_array($value) && isset($value[0]) && count($value) == 2) {
                list($exp, $data) = $value;
                $exp              = strtolower($exp);
            } elseif (is_numeric($field) && !is_array($value)) {
                $exp  = 'string';
                $data = $value;
            } elseif (!is_numeric($field) && !is_array($value)) {
                $exp  = 'equal';
                $data = $value;
            } else {
                throw new Exception('SQL ERROR ParseSetData:' . var_export($this->options['data'], 1));
            }

            $buildField = ':' . str_replace('.', '_', $field); // 预加载名称
            $field      = $this->addFieldTag($field);

            switch ($exp) {
                case 'add':
                    $this->bulid['data'][$field] = $field . ' = ' . $field . ' + ' . $buildField;
                    break;
                case 'less':
                    $this->bulid['data'][$field] = $field . ' = ' . $field . ' -' . $buildField;
                    break;
                case 'concat':
                    $this->bulid['data'][$field] = $field . ' = CONCAT(' . $field . ',' . $buildField . ')';
                    break;
                case 'json':
                    $this->bulid['data'][$field] = $field . ' = ' . $buildField;
                    break;
                case 'equal':
                    $this->bulid['data'][$field] = $field . ' = ' . $buildField;
                    break;
                case 'string':
                    $this->bulid['data'][$field] = $value;
                    break;
                default:
                    throw new Exception('SQL ERROR ParseSetData: [ ' . $exp . ' ] [' . $value . ']');
                    break;
            }

            if ($exp !== 'string') {
                $this->setBuildParam($field, $buildField, $data);
            }

        }

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
        $this->bulid['having'] = ' HAVING ' . $this->addFieldTag($this->options['having']);

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

                // 将查询结果存入临时表
                if ($this->options['tmp']) {

                    $name = $this->build['tmp'] !== true ? $this->options['tmp'] : $this->bulid['table'];

                    $this->bulid['sql'] = 'DROP TEMPORARY TABLE IF EXISTS' . $this->build['tmp'] . '; CREATE TEMPORARY TABLE ' . $this->build['tmp'] . ' AS (' . $this->bulid['sql'] . ');';
                }
                break;
            case 'UPDATE':
                $this->bulid['sql'] = 'UPDATE ' . $this->bulid['table'] . ' SET ' . implode(',', $this->parseSetData());
                break;
            case 'INSERT':
                $this->bulid['sql'] = 'INSERT INTO ' . $this->bulid['table'];

                $names  = [];
                $values = [];
                $params = $this->parseSetData();
                foreach ($params as $item) {
                    $data = explode('=', $item);
                    if (count($data) == 2) {
                        array_push($names, $data[0]);
                        array_push($values, $data[1]);
                    }
                }

                $this->bulid['sql'] = 'INSERT INTO ' . $this->bulid['table'] . '(' . implode(',', $names) . ')VALUE(' . implode(',', $values) . ')';

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

        return $this->bulid['sql'];
    }

    /** 查询数据表信息 */
    public function getTableStatus($field = '')
    {

        $this->connect();
        $this->parseTable();

        $this->field($field);
        $this->parseField();

        $this->bulid['sql'] = 'SHOW TABLE STATUS WHERE NAME = \'' . $this->bulid['table'] . '\'';
        $result             = $this->query($this->bulid['sql']);
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

    /**
     * 获取单个字段内容
     * @date   2018-04-06T21:35:17+0800
     * @author ChenMingjiang
     * @param  [type]                   $field [description]
     * @return [type]                          [description]
     */
    public function value(string $field = '')
    {
        // 执行获取缓存数据
        if (($result = $this->getCache(__FUNCTION__)) !== false) {
            return $result;
        }

        if ($field) {
            $this->field($field);
        }

        $this->limit(1);

        $result = $this->query($this->bulidSql('SELECT'), $this->bulid['params']);

        if ($result) {
            $data = $result->fetchColumn();
        }

        // 如果开启缓存则保存缓存
        return $this->setCache($data ?: '');

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
        // 执行获取缓存数据
        if (($result = $this->getCache(__FUNCTION__)) !== false) {
            return $result;
        }

        if ($field) {
            $this->field($field);
        }

        $result = $this->query($this->bulidSql('SELECT'), $this->bulid['params']);

        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            if (count($this->options['field']) == 2) {
                $data[$row[1]] = $row[0];
            } else {
                $data[] = $row[0];
            }
        }

        // 如果开启缓存则保存缓存
        return $this->setCache($data ?? []);
    }

    /**
     * 单条查询
     * @date   2018-07-13T11:01:05+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function find($field = '')
    {
        // 执行获取缓存数据
        if (($result = $this->getCache(__FUNCTION__)) !== false) {
            return $result;
        }

        if ($field) {
            $this->field($field);
        }

        $this->limit(1);

        $result = $this->query($this->bulidSql('SELECT'), $this->bulid['params']);

        $data = $result->fetch(PDO::FETCH_ASSOC);

        // 如果开启缓存则保存缓存
        return $this->setCache($data);

    }

    /** 数组索引 */
    public function lists($field = '*')
    {
        // 执行获取缓存数据
        if (($result = $this->getCache(__FUNCTION__)) !== false) {
            return $result;
        }

        if ($field) {
            $this->field($field);
        }

        $this->limit(1);

        $result = $this->query($this->bulidSql('SELECT'), $this->bulid['params']);

        $data = $result->fetch(PDO::FETCH_NUM);
        if ($data == false) {
            foreach ($this->options['field'] as $value) {
                $data[$value] = '';
            }
        }

        // 如果开启缓存则保存缓存
        return $this->setCache($data);
    }

    /**
     * 多条查询
     * @date   2018-07-13T11:01:13+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function select()
    {

        // 执行获取缓存数据
        if (($result = $this->getCache(__FUNCTION__)) !== false) {
            return $result;
        }

        $result = $this->query($this->bulidSql('SELECT'), $this->bulid['params']);

        $data = $result->fetchAll(PDO::FETCH_ASSOC);

        // 如果开启缓存则保存缓存
        return $this->setCache($data);
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
        // 执行获取缓存数据
        if (($result = $this->getCache(__FUNCTION__)) !== false) {
            return $result;
        }

        if ($value) {
            $this->field($value);
        }

        $result = $this->query($this->bulidSql('COUNT'), $this->bulid['params']);
        $data   = $result->fetchColumn();

        // 如果开启缓存则保存缓存
        return $this->setCache((int) $data);

    }

    /** 添加 */
    public function add($data = '', $exp = null, $value = null)
    {

        $this->setData($data, $exp, $value);

        $result = $this->query($this->bulidSql('INSERT'), $this->bulid['params']);

        return $result === false ? false : $this->getLastInsertId();
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

        $result = $this->query($this->bulidSql('UPDATE'), $this->bulid['params']);

        if (!$result) {
            return false;
        }

        $num = $result->rowCount();

        return $num;
    }

    /** 删除 */
    public function delete()
    {

        if (empty($this->options['map'])) {
            throw new Exception('SQL ERROR : 禁止全表删除');
        }

        $result = $this->query($this->bulidSql('DELETE'), $this->bulid['params']);
        $num    = $result->rowCount();

        return $num;
    }

    // 开启事务
    public function startTrans()
    {

        ++$this->transactions; // 开启事务标签

        if ($this->transactions == 1) {
            $this->connect(); // 链接数据库
            $this->link->beginTransaction();
        }

    }

    // 回滚事务
    public function rollback()
    {

        if ($this->transactions == 1) {
            $this->connect(); // 链接数据库
            $this->link->rollBack();
        } else {
            --$this->transactions;
        }

    }

    // 提交事务
    public function commit()
    {
        // 链接数据库
        if ($this->transactions == 1) {
            $this->connect();
            $this->link->commit();
        } else {
            --$this->transactions;
        }

    }

    /**
     * 数据库缓存
     * @date   2020-03-31T09:15:55+0800
     * @author ChenMingjiang
     * @param  string                   $key [description]
     * @param  integer                  $ttl [description]
     * @return [type]                   [description]
     */
    public function cache($key = false, $ttl = 3600)
    {
        if ($key) {
            $this->options['cache']['key'] = (bool) $key;
        }

        if (is_numeric($key)) {
            $this->options['cache']['ttl'] = $key;
        } else {
            $this->options['cache']['ttl'] = $ttl;
        }

        return $this;
    }

    /**
     * 获取数据缓存
     * @date   2020-04-10T16:07:09+0800
     * @author ChenMingjiang
     * @param  [type]                   $prefix [缓存前缀]
     * @return [type]                   [description]
     */
    public function getCache($prefix)
    {
        if (isset($this->options['cache']['key']) && $this->options['cache']['key'] === true && $this->options['map']) {

            $this->options['cache']['key'] = $prefix . md5(json_encode($this->options['map']) . json_encode($this->bulid['params']));

            if (Cache::has($this->options['cache']['key'])) {
                return Cache::get($this->options['cache']['key']);
            }
        }

        return false;
    }

    /**
     * 数据结构缓存
     * @date   2020-04-10T16:07:21+0800
     * @author ChenMingjiang
     * @param  [type]                   $result [缓存值]
     */
    public function setCache($result)
    {
        // 存在缓存key 并且是读操作
        if (isset($this->options['cache']['key']) && $this->options['cache']['key'] && in_array($this->options['type'], $this->power['read'])) {
            Cache::set($this->options['cache']['key'], $result, $this->options['cache']['ttl']);
        }

        return $result;
    }

    /**
     * 执行sql命令
     * @date   2020-04-11T08:44:27+0800
     * @author ChenMingjiang
     * @param  [type]                   $sql     [description]
     * @param  array                    $params  [description]
     * @return [type]                   [description]
     */
    public function query($sql, array $params = [])
    {

        // 链接数据库
        $this->connect();

        $this->debug(true);

        try {
            if ($this->PDOStatement) {
                $this->PDOStatement = null;
            }

            $this->PDOStatement = $this->link->prepare($sql);

            // 绑定预加载参数
            if ($params) {
                foreach ($params as $bulids) {
                    foreach ($bulids as $item) {
                        $this->buildParam($item);
                    }
                }
            }

            // 执行结果
            $result = $this->PDOStatement->execute();

        } catch (\Exception $e) {
            throw new Exception('line:' . $e->getLine() . ' error:' . $e->getMessage() . ' Sql:' . $this->getLastsql() . var_export($this->bulid['sql'], 1) . var_export($this->bulid['params'], 1));
        }

        $this->debug(false, $result);

        return $this->PDOStatement;

    }

    /**
     * 调试模式
     * @date   2020-04-10T17:36:21+0800
     * @author ChenMingjiang
     * @param  [type]                   $start  [description]
     * @param  [type]                   $result [description]
     * @return [type]                   [description]
     */
    protected function debug($start, $result = null)
    {
        static $_beginTime, $_endTime;

        if ($start) {
            // 调试模式
            if (isset($this->id['sql_explain']) && $this->id['sql_explain'] && $this->link) {
                $this->explain();
            }

            $_beginTime = microtime(true);
        } else {
            // 记录操作结束时间
            $_endTime = microtime(true);

            $this->info['time'] = $_endTime - $_beginTime; // 获取执行时间
            $this->info['sql']  = $this->getLastsql(); // 执行Sql

            // 调试模式
            Trace::addSql($this->info);

            // 执行失败
            if ($result === false) {
                // 存入文件
                if (Config::get('debug') || !empty($this->id['error_log'])) {
                    Log::error('SQL ERROR :' . $this->PDOStatement->errorinfo()[2] . ' SQL :  ' . $this->getLastsql());
                }

                if (Config::get('debug')) {
                    throw new Exception('SQL ERROR :' . $this->PDOStatement->errorinfo()[2] . ' SQL :  ' . $this->getLastsql() . PHP_EOL);
                }
            }

        }

    }

    /**
     * 根据参数绑定组装最终的SQL语句 便于调试
     * @access public
     * @param string    $sql    带参数绑定的sql语句
     * @param array     $binds  参数绑定列表
     * @return string
     */
    public function getRealSql($sql, array $binds = [])
    {

        foreach ($binds as $bind) {

            foreach ($bind as $item) {
                list($name, $value, $type) = array_values($item);

                if ($type == 'int') {
                    $value = (int) $value;
                } elseif ($type == 'bool') {
                    $value = (bool) $value;
                } else {
                    $value = '\'' . $value . '\'';
                }

                $sql = str_replace($name . ' ', $value . ' ', $sql);
            }

        }

        return $sql;
    }

}
