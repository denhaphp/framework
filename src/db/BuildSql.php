<?php
namespace denha\db;

use denha;
use denha\Route;
use denha\Trace;
use \Exception;
use \PDO;

class BuildSql
{

    public static $dbConfig;
    public static $instance; // 单例实例化;
    public static $do; // 数据库操作符
    public $id; // 当前链接配置ID
    public $link; // 当前链接符
    public $options; // 记录参数信息
    public $bulid; // 记录构造Sql;

    private function __construct($dbConfig = '')
    {
        $this->config($dbConfig);

    }

    //单例实例化 避免重复New暂用资源
    public static function getInstance($dbConfig = '')
    {
        if (is_null(self::$instance)) {
            self::$instance = new BuildSql($dbConfig);
        }

        return self::$instance;

    }

    public function config($dbConfig = [])
    {
        if ($dbConfig) {
            self::$dbConfig = $dbConfig;
        } else {
            self::$dbConfig = config('dbConfig', 'db');
        }

        foreach (self::$dbConfig as $key => $value) {
            $hash = md5(json_encode(self::$dbConfig[$key]));
            if (!isset(self::$do[$hash])) {
                self::$do[$hash] = $this->open($value);
            }

        }
    }

    public function parseDNS($config)
    {
        switch ($config['type']) {
            case 'mysql':
            case 'mysqli':
                $dns = 'mysql:host=' . $config['host'] . ';dbname=' . $config['name'];
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
    public function open($config)
    {
        $config['user'] = isset($config['user']) ? $config['user'] : '';
        $config['pwd']  = isset($config['pwd']) ? $config['pwd'] : '';

        try {
            $do = new PDO($this->parseDNS($config), $config['user'], $config['pwd']);
        } catch (\PDOException $e) {
            $msg = $e->getMessage() . ' <br/>SQL Config:<br/>' . PHP_EOL;
            foreach ($config as $key => $value) {
                $msg .= $key . ' : <font style="color:red">' . $value . '</font><br/>' . PHP_EOL;
            }
            throw new Exception($msg);
        }

        $do->exec('set names ' . $config['charset']);

        return $do;

    }

    /** 获取最后执行SQL */
    public function getLastSql()
    {
        return $this->sqlInfo['sql'];
    }

    /** 链接 */
    public function connect($id = 0)
    {
        $this->id       = $id;
        $this->link     = self::$do[md5(json_encode(self::$dbConfig[$id]))];
        $this->tablepre = self::$dbConfig[$id]['prefix'];

        if (!$this->link) {
            throw new Exception('链接信息异常');
        }

        $this->options['tablepre'] = self::$dbConfig[$id]['prefix'];
        $this->options['database'] = self::$dbConfig[$id]['name'];

        return $this;
    }

    /** 构造Sql初始化 */
    public function init()
    {

        $this->bulid = [
            'field'     => '*',
            'sql'       => '',
            'chilidSql' => false,
            'data'      => '',
        ];

        $this->options = [
            'data'  => [],
            'field' => [],
        ];

        $this->connect();
    }

    /**
     * 数据表
     * @date   2018-07-12T11:08:29+0800
     * @author ChenMingjiang
     * @param  [type]                   $table   [description]
     * @param  array                    $options [description]
     * @return [type]                            [description]
     */
    public function table($table, $options = array())
    {

        $isTablepre = isset($options['is_tablepre']) ? $options['is_tablepre'] : true;
        $link       = isset($options['link']) ? $options['link'] : '';

        $this->init();

        if ($link) {
            $this->connect($link);
        }

        $this->table = parseName($table);
        if ($isTablepre && $this->options['tablepre']) {
            $this->options['table'] = [
                'name'        => $this->options['tablepre'] . parseName($table),
                'is_tablepre' => $isTablepre,
            ];
        } else {
            $this->options['table'] = [
                'name'        => parseName($table),
                'is_tablepre' => $isTablepre,
            ];
        }

        $this->bulid['table'] = $this->options['table']['name'];

        return $this;
    }

    /**
     * 获取表名称
     * @date   2017-06-10T22:56:01+0800
     * @author ChenMingjiang
     * @param  [type]                   $table [description]
     * @return [type]                          [description]
     */
    public function getTableName()
    {
        return $this->bulid['table'];
    }

    /**
     * 字段信息增加`标记
     * @date   2018-07-12T11:10:38+0800
     * @author ChenMingjiang
     * @param  [type]                   $field [description]
     */
    private function addFieldTag($field)
    {
        if (strripos($field, '`') === false && $field != '_string' && strripos($field, '*') === false && strripos($field, 'concat') === false) {
            $field = strripos($field, '.') !== false ? str_replace('.', '.`', $field) . '`' : '`' . $field . '`';
        }

        return $field;
    }

    /**
     * 查询条件
     * @date   2018-06-29T15:50:51+0800
     * @author ChenMingjiang
     * @param  [type]                   $where [description]
     * @param  [type]                   $value [description]
     * @param  [type]                   $exp   [description]
     * @return [type]                          [description]
     */
    public function where($where, $value = null, $exp = null)
    {

        $expRule[0] = ['>', '<', '>=', '<=', '!=', 'like', '<>'];
        $expRule[1] = ['in', 'not in', 'IN', 'NOT IN'];
        $expRule[2] = ['instr', 'INSTR'];
        $expRule[3] = ['between', 'BETWEEN'];
        $expRule[4] = ['or', 'OR'];

        if (!$where) {
            return $this;
        }

        $newWhere = '';

        if ($value !== null && $exp !== null) {
            $map[$where] = [$value, $exp];
            $where       = $map;
        } elseif ($value !== null && $exp === null) {
            $map[$where] = $value;
            $where       = $map;
        }

        if (is_array($where)) {
            foreach ($where as $mapField => $v) {

                //记录条件参数
                $this->options['map'][] = [$mapField => $v];

                $mapField = $this->addFieldTag($mapField);

                if (is_array($v)) {

                    list($mapExp, $mapValue) = $v;

                    if (in_array($mapExp, $expRule[0])) {
                        $newWhere .= $mapField . '  ' . $mapExp . ' \'' . $mapValue . '\' AND ';
                    } elseif (in_array($mapExp, $expRule[1])) {
                        if (!$mapValue) {
                            $newWhere .= $mapField . '  ' . $mapExp . ' (\'\') AND ';
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
                            $newWhere .= $mapField . '  ' . $mapExp . ' (' . $commonInValue . ') AND ';
                        }
                    } elseif (in_array($mapExp, $expRule[2])) {
                        $newWhere .= $mapExp . '(' . $mapField . ',\'' . $mapValue . '\') AND ';
                    } elseif (in_array($mapExp, $expRule[3])) {
                        $newWhere .= $mapField . '  ' . $mapExp . ' \'' . $mapValue . '\' AND \'' . $v[2] . '\' AND ';
                    } elseif (in_array($mapExp, $expRule[4])) {
                        $newWhere .= $mapField . ' = \'' . $mapValue . '\' OR ';
                    }
                } elseif ($mapField == '_string') {
                    $newWhere .= $v . ' AND ';
                } else {
                    $newWhere .= $mapField . ' = \'' . $v . '\' AND ';
                }
            }
        } else {
            //记录条件参数
            $this->options['map'][] = $where;
        }

        if (!isset($this->bulid['where'])) {
            $this->bulid['where'] = ' WHERE ' . substr($newWhere, 0, -4);
        } else {
            $this->bulid['where'] .= ' AND ' . substr($newWhere, 0, -4);
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
    public function join($table, $where = '', $float = 'left')
    {

        if ($table == $this->options['table']['name']) {
            throw new Exception('表与关联表名字相同');
        }

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
    public function limit($limit = 0, $pageSize = '')
    {
        $this->bulid['limit'] = ' LIMIT ' . $limit;
        if ($pageSize) {
            $this->bulid['limit'] = ' LIMIT ' . $limit . ',' . $pageSize;
        }

        $this->options['limit'] = array('limit' => $limit, 'pageSize' => $pageSize);

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

        $this->bulid['group'] = '';
        foreach ($this->options['group'] as $val) {
            $this->bulid['group'] .= $this->addFieldTag($val) . ',';
        }

        $this->bulid['group'] = ' GROUP BY ' . substr($this->bulid['group'], 0, -1);

        return $this;
    }

    /** 排序 */
    public function order($value)
    {
        if (!$value) {
            return $this;
        }

        $this->options['order'] = is_array($value) ? $value : explode(',', $value);

        $this->bulid['order'] = '';

        foreach ($this->options['order'] as $val) {
            if ($val) {
                list($field, $exp) = explode(' ', $val);
                $this->bulid['order'] .= ' ' . $this->addFieldTag($field) . ' ' . $exp . ',';
            }
        }

        $this->bulid['order'] = ' ORDER BY ' . substr($this->bulid['order'], 0, -1);

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
    public function childSql($value = false)
    {
        $this->options['childSql'] = $value;

        return $this;
    }

    /**
     * 子查询table
     * @date   2017-11-22T00:45:38+0800
     * @author ChenMingjiang
     * @param  [type]                   $table [description]
     * @return [type]                          [description]
     */
    public function childSqlQuery($table)
    {
        $this->table = '(' . $table . ') as child';

        return $this;
    }

    /** 构建SQL语句 */
    public function bulidSql($type = 'SELECT')
    {
        $this->options['type'] = $type;

        if (!$this->bulid['table']) {
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
        empty($this->bulid['where']) ?: $this->bulid['sql'] .= $this->bulid['where'];
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
        $where              = ' WHERE table_name = \'' . $this->bulid['table'] . '\'';
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
    public function value($field = '')
    {

        if ($field) {
            $this->field($field);
        }

        $this->limit(1);
        $this->bulidSql('SELECT');

        $result = $this->query();
        $data   = $result->fetchColumn();

        return $data;
    }

    /**
     * 获取单个字段列表
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
        $id     = $this->link->lastInsertId();

        return $id;
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

        if (empty($this->bulid['where'])) {
            throw new Exception('SQL ERROR : 禁止全表删除');
        }

        $this->bulidSql('DELETE');
        $result = $this->query();
        $num    = $result->rowCount();

        return $num;
    }

    //开启事务
    public function startTrans()
    {
        $this->link->beginTransaction();
        return true;
    }

    //回滚事务
    public function rollback()
    {
        $this->link->rollBack();
        return true;
    }

    //提交事务
    public function commit()
    {
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

        $_beginTime = microtime(true);
        $result     = $this->link->query($this->bulid['sql']);
        $_endTime   = microtime(true);

        $this->sqlInfo['time'] = $_endTime - $_beginTime; //获取执行时间
        $this->sqlInfo['sql']  = $this->bulid['sql'];

        if ($result) {
            Trace::addSqlInfo($this->sqlInfo); //存入调试信息中
            //存入文件中
            if (self::$dbConfig[$this->id]['save_log']) {
                $this->addSqlLog();
            }
            return $result;
        } else {
            //存入文件
            if (self::$dbConfig[$this->id]['error_log']) {
                $this->addErrorSqlLog();
            }

            if (config('debug')) {
                throw new Exception('SQL ERROR : ' . $this->bulid['sql']);
            }

            if (config('trace')) {
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
            $path = DATA_PATH . 'sql_log' . DS . $this->options['database'] . DS;
            is_dir($path) ? '' : mkdir($path, 0755, true);

            $path .= 'error_' . date('Y_m_d_H', TIME) . '.text';

            $info = '------ ' . $this->sqlInfo['time'];
            $info .= ' | ' . date('Y-m-d H:i:s', TIME);
            $info .= ' | ip:' . getIP();
            $info .= ' | Url:' . URL . '/' . Route::$uri;
            $info .= PHP_EOL;

            $content = $this->sqlInfo['sql'] . ';' . PHP_EOL . '来源：' . $_SERVER['HTTP_USER_AGENT'] . PHP_EOL . '--------------' . PHP_EOL;

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
        //如果没有写入权限尝试修改权限 如果修改后还是失败 则跳过
        if (!isWritable(DATA_PATH)) {
            return false;
        }

        //创建文件夹
        is_dir(DATA_PATH . 'sql_log') ? '' : mkdir(DATA_PATH . 'sql_log', 0755, true);

        $info = '------ ' . $this->sqlInfo['time'];
        $info .= ' | ' . date('Y-m-d H:i:s', TIME);
        $info .= ' | ip:' . getIP();
        $info .= ' | Url:' . URL . '/' . Route::$uri;
        $info .= PHP_EOL;

        //记录sql
        if ($this->sqlInfo && self::$dbConfig[$this->id]['save_log']) {
            $path = DATA_PATH . 'sql_log' . DS . $this->options['database'] . DS;
            is_dir($path) ? '' : mkdir($path, 0755, true);
            $content = $this->sqlInfo['sql'] . PHP_EOL;

            $path .= isset($this->options['type']) ? strtolower($this->options['type']) : 'other';
            $path .= '_' . date('Y_m_d_H', TIME) . '.text';

            //记录慢sql
            if (self::$dbConfig[$this->id]['slow_log']) {
                if ($this->sqlInfo['time'] > self::$dbConfig[$this->id]['slow_time']) {
                    $path = 'slow_' . $text;
                }
            }

            error_log($content . $info, 3, $path);
        }
    }

}
