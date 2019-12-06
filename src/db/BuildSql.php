<?php
//------------------------
//· 数据库操作类
//---------------------
namespace denha\db;

use denha;
use denha\Config;
use denha\Route;
use denha\Trace;
use \Exception;
use \PDO;

class BuildSql
{

    private static $dbConfig;
    private static $instance; // 单例实例化;
    private static $do; // 数据库操作符

    public static $link; // 当前链接符
    public static $id; // 当前链接配置ID

    public $options; // 记录参数信息$
    public $bulid; // 记录构造Sql;

    private $config; //

    private function __construct(array $dbConfig = [])
    {
        $this->config($dbConfig);

    }

    // 单例实例化 避免重复New占用资源
    public static function getInstance(array $dbConfig = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new BuildSql($dbConfig);
        }

        return self::$instance;

    }

    public function config(array $dbConfig = [])
    {
        if ($dbConfig) {
            self::$dbConfig = $dbConfig;
        } else {
            self::$dbConfig = Config::includes('db.php')['dbInfo'];
        }

        foreach (self::$dbConfig as $key => $value) {
            $hash = md5(json_encode(self::$dbConfig[$key]));
            if (!isset(self::$do[$hash])) {
                self::$do[$hash] = $this->open($value);
            }
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
            $msg = $e->getMessage() . ' <br/>SQL Config:<br/>' . PHP_EOL;
            if (Config::get('debug')) {
                foreach ($config as $key => $value) {
                    $msg .= $key . ' : <font style="color:red">' . $value . '</font><br/>' . PHP_EOL;
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
    public function connect(int $id = 0)
    {
        self::$id   = $id;
        self::$link = self::$do[md5(json_encode(self::$dbConfig[$id]))];

        if (!self::$link->getAttribute(PDO::ATTR_SERVER_INFO)) {
            throw new Exception('链接信息异常');
        }

        $this->config              = self::$dbConfig[$id];
        $this->tablepre            = $this->config['prefix'];
        $this->options['tablepre'] = $this->config['prefix'];
        $this->options['database'] = $this->config['name'];

        return $this;
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
            'data'  => [],
            'field' => [],
            'order' => [],
            'limit' => [],
            'table' => [],
        ];

        $this->connect();
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
    public function table(string $table, array $options = [])
    {

        $link = isset($options['link']) ? $options['link'] : '';

        // 初始化SQL参数
        $this->init();

        // 链接其他数据库
        if ($link) {
            $this->connect($link);
        }

        $this->parseTable($table, $options); // 解析table

        return $this;
    }

    public function parseTable($table, $options = []): string
    {
        $isTablepre = isset($options['is_tablepre']) ? $options['is_tablepre'] : true;

        // 判断是否存在 as
        if (stripos($table, ' as ') !== false) {
            list($tableName, $tableAs) = explode(' AS ', str_replace(' as ', ' AS ', $table));
        } else {
            $tableName = $table;
        }

        $tableName = parseName(trim($tableName));
        $tableAs   = isset($tableAs) ? trim($tableAs) : '';

        $options['name']        = $isTablepre && $this->options['tablepre'] ? $this->options['tablepre'] . $tableName : $tableName;
        $options['is_tablepre'] = $isTablepre;
        $options['as']          = $tableAs;

        if ($tableAs) {
            $bulid = $options['name'] . ' AS ' . $tableAs;
        } else {
            $bulid = $options['name'];
        }

        // 保证 $this->options['table'] 唯一
        if (empty($this->options['table'])) {
            $this->options['table'] = $options;
            $this->bulid['table']   = $bulid;
        }

        return $bulid;

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
            // if (array_key_last($whereMap) == $key) { PHP >= 7.3
            // PHP <= 7.3
            if (count($whereMap) - 1 == $key) {

                $mapSql .= $field;
            } else {
                $mapSql .= $field . $link;
            }
        }

        return $mapSql;
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
                // 格式化字段
                $mapField = $this->addFieldTag($mapField);
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

    /** 处理查询条件 */
    public function parseMap($mapField, $mapExp, $mapValue, $mapLink = 'AND'): array
    {

        $mapLink = ' ' . $mapLink . ' '; // 连接符
        $expRule = [
            ['>', '<', '>=', '<=', '!=', 'like', '<>', '='],
            ['in', 'not in', 'IN', 'NOT IN'],
            ['instr', 'INSTR'],
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

        if ($table == $this->options['table']['name']) {
            throw new Exception('表与关联表名字相同');
        }

        $table = $this->parseTable($table, ['is_tablepre' => false]);

        $where ?: $where = $this->options['table']['name'] . '.id = ' . $table . '.id';

        $this->options['join'] = ['table' => $table, 'where' => $where, 'float' => $float];

        if (!isset($this->bulid['join'])) {
            $this->bulid['join'] = ' ' . $float . ' JOIN ' . $table . ' ON ' . $where;
        } else {
            $this->bulid['join'] .= ' ' . $float . ' JOIN ' . $table . ' ON ' . $where;
        }

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
        $this->bulid['limit'] = ' LIMIT ' . $limit;
        if ($pageSize) {
            $this->bulid['limit'] = ' LIMIT ' . $limit . ',' . $pageSize;
        }

        $this->options['limit'] = ['limit' => $limit, 'pageSize' => $pageSize];

        return $this;
    }

    /** 查询字段 */
    public function field($field = '*')
    {

        if (!$field) {
            return $this;
        }

        $this->options['field'] = is_array($field) ? $field : explode(',', $field);

        $this->bulid['field'] = '';
        foreach ($this->options['field'] as $val) {
            $this->bulid['field'] .= $this->addFieldTag($val) . ',';
        }

        $this->bulid['field'] = substr($this->bulid['field'], 0, -1);

        return $this;
    }

    /** 编辑字段 */
    public function data($data, $exp = null, $value = null)
    {
        if ($exp !== null && $value === null) {
            $data = [$data => $exp];
        } elseif ($value !== null) {
            $data = [$data => [$exp, $value]];
        }

        $data = is_array($data) ? $data : explode(',', $data);

        $this->options['data'] = $data;
        $this->bulid['data']   = '';

        foreach ($data as $k => $v) {
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

        return $this;
    }

    /** 分组 */
    public function group($value = '')
    {

        if (!$value) {
            return $this;
        }

        $this->options['group'] = is_array($value) ? $value : explode(',', $value);

        foreach ($this->options['group'] as $val) {
            $this->bulid['group'] .= $this->addFieldTag($val) . ',';
        }

        if (!$this->bulid['order']) {
            $this->bulid['group'] = ' GROUP BY ' . substr($this->bulid['group'], 0, -1);
        } else {
            $this->bulid['group'] = ' , ' . substr($this->bulid['group'], 0, -1);
        }

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

        if (!is_array($field) && stripos($field, '(') === false) {
            $parseOrder = explode(',', $field);
        } else {
            $parseOrder[] = $field;
        }

        foreach ($parseOrder as $key => $val) {

            // 包含 () 的关键字
            if (stripos($val, '(') !== false) {
                $parseOrder[$key] = ' ' . $val;
            } else {
                list($field, $exp) = explode(' ', $val);
                $parseOrder[$key]  = ' ' . $this->addFieldTag($field) . ' ' . $exp;
            }

        }

        if ($parseOrder) {

            $this->options['order'] = array_merge($parseOrder, $this->options['order']);

            if (!$this->bulid['order']) {
                $this->bulid['order'] = ' ORDER BY ' . implode(',', $parseOrder);
            } else {
                $this->bulid['order'] .= ' , ' . implode(',', $parseOrder);
            }

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
        $this->bulid['having']   = ' HAVING ' . $this->addFieldTag($field);
        return $this;
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

        if (empty($this->bulid['table'])) {
            throw new Exception('请选择数据表');
        }

        if ($type == 'SELECT') {
            $this->bulid['sql'] = 'SELECT ' . $this->bulid['field'] . ' FROM ' . $this->bulid['table'];
        } elseif ($type == 'UPDATE') {
            $this->bulid['sql'] = 'UPDATE ' . $this->bulid['table'] . ' SET ' . $this->bulid['data'];
        } elseif ($type == 'INSERT') {
            $this->bulid['sql'] = 'INSERT INTO ' . $this->bulid['table'] . ' SET ' . $this->bulid['data'];
        } elseif ($type == 'DELETE') {
            $this->bulid['sql'] = 'DELETE FROM ' . $this->bulid['table'];
        } elseif ($type == 'COUNT') {
            $this->bulid['sql'] = 'SELECT  COUNT(' . $this->bulid['field'] . ') AS  t  FROM ' . $this->bulid['table'];
        }

        empty($this->bulid['join']) ?: $this->bulid['sql'] .= $this->bulid['join'];

        empty($this->options['map']) ?: $this->bulid['sql'] .= $this->parseWhere();

        empty($this->bulid['group']) ?: $this->bulid['sql'] .= $this->bulid['group'];
        empty($this->bulid['having']) ?: $this->bulid['sql'] .= $this->bulid['having'];
        empty($this->bulid['order']) ?: $this->bulid['sql'] .= $this->bulid['order'];
        empty($this->bulid['limit']) ?: $this->bulid['sql'] .= $this->bulid['limit'];

    }

    /** 查询数据表信息 */
    public function getTableStatus($field = '')
    {

        $this->field($field);
        $this->bulid['sql'] = 'SHOW TABLE STATUS WHERE NAME = \'' . $this->bulid['table'] . '\'';
        $result             = $this->query();
        $list               = $result->fetch(PDO::FETCH_ASSOC);

        if (count($this->options['field']) == 1) {
            foreach ($list as $key => $value) {
                if (!isset($list[$field])) {
                    throw new Exception('SQL ERROR : 字段信息不存在 [' . $field . ']');
                }

                $data = $list[$field];
            }
        } else {
            $data = $list;
        }

        return $data;
    }

    /** 查询表字段名 */
    public function getField($field = 'COLUMN_NAME')
    {
        $this->field($field);
        $where = ' WHERE table_name = \'' . $this->bulid['table'] . '\'';
        if ($this->bulid['group']) {
            $where .= $this->bulid['group'];
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

        return !empty($data) ? $data : '';

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

        return isset($data) ? $data : [];
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

        $this->data($data, $exp, $value);

        $this->bulidSql('INSERT');
        $result = $this->query();
        $id     = self::$link->lastInsertId();

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
        $this->data($data, $exp, $value);

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
        self::$link->beginTransaction();
        return true;
    }

    // 回滚事务
    public function rollback()
    {
        self::$link->rollBack();
        return true;
    }

    // 提交事务
    public function commit()
    {
        self::$link->commit();
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

        // 存入Sql Explain信息
        if (!empty($this->config['sql_explain'])) {
            $res = self::$link->query('explain ' . $this->bulid['sql']);
            if ($res) {
                $this->sqlInfo['explain'] = $res->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        $_beginTime = microtime(true);
        $result     = self::$link->query($this->bulid['sql']);
        $_endTime   = microtime(true);

        $this->sqlInfo['time'] = $_endTime - $_beginTime; // 获取执行时间
        $this->sqlInfo['sql']  = $this->bulid['sql'];

        // 执行成功
        if ($result) {

            // 存入调试信息中
            Trace::addSqlInfo($this->sqlInfo);
            // 存入文件中
            if ($this->config['save_log']) {
                $this->addSqlLog();
            }

            return $result;
        } else {

            // 存入文件
            if ($this->config['error_log']) {
                $this->addErrorSqlLog();
            }

            if (Config::get('debug')) {
                list($errorCode, $errorNumber, $errorMsg) = self::$link->errorInfo();
                throw new Exception('[<font color="red">SQL ERROR :' . $errorCode . ' ' . $errorMsg . ' </font>] SQL :  ' . $this->bulid['sql'] . PHP_EOL);
            }

            if (Config::get('trace')) {
                Trace::addErrorInfo('[SQL ERROR] ' . $this->bulid['sql']);

            }

            return false;

        }

    }

    /** 保存错误SQL记录 */
    public function addErrorSqlLog()
    {

        //如果没有写入权限尝试修改权限 如果修改后还是失败 则跳过
        if (isWritable(DATA_PATH)) {
            $path = DATA_SQL_PATH . $this->options['database'] . DS;
            is_dir($path) ? '' : mkdir($path, 0755, true);

            $path .= 'error_' . date('Y_m_d', TIME) . '.text';

            $info = '------ ' . $this->sqlInfo['time'];
            $info .= ' | ' . date('Y-m-d H:i:s', TIME);
            $info .= ' | ip:' . Config::IP();
            $info .= ' | Url:' . URL . '/' . Route::$uri;
            $info .= PHP_EOL;

            $content = $this->sqlInfo['sql'] . ';' . PHP_EOL . '--------------' . PHP_EOL;

            error_log($content . $info, 3, $path);

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
        // 如果没有写入权限尝试修改权限 如果修改后还是失败 则跳过
        if (!isWritable(DATA_PATH)) {
            return false;
        }

        //创建文件夹
        is_dir(DATA_SQL_PATH) ? '' : mkdir(DATA_SQL_PATH, 0755, true);

        $info = '------ ' . $this->sqlInfo['time'];
        $info .= ' | ' . date('Y-m-d H:i:s', TIME);
        $info .= ' | ip:' . Config::IP();
        $info .= ' | Url:' . URL . '/' . Route::$uri;
        $info .= PHP_EOL . PHP_EOL;

        // 如果存在sql信息 并且开启日志记录
        if ($this->sqlInfo && $this->config['save_log']) {
            $basePath = DATA_SQL_PATH . $this->options['database'] . DS;
            is_dir($basePath) ? '' : mkdir($basePath, 0755, true);

            $content = $this->sqlInfo['sql'] . PHP_EOL;

            // 记录explain
            if ($this->config['sql_explain'] && isset($this->sqlInfo['explain'])) {
                foreach ($this->sqlInfo['explain'] as $explain) {
                    $content .= json_encode($explain) . PHP_EOL;
                }
            }

            $path = $basePath . (isset($this->options['type']) ? strtolower($this->options['type']) : 'other');
            $path .= '_' . date('Y_m_d', TIME) . '.text';

            // 记录慢sql
            if ($this->config['slow_log']) {
                if ($this->sqlInfo['time'] > $this->config['slow_time']) {
                    $path = $basePath . 'slow_' . date('Y_m_d', TIME) . '.text';
                }
            }

            error_log($content . $info, 3, $path);
        }
    }

}
