<?php
namespace denha;

use denha;

class Mysqli
{

    private static $instance;
    public static $options; //记录参数信息

    public static $link; //mysql链接信息

    public $dbConfig; //数据库连接信息
    public $tablepre; //表前缀
    public $build; //保存未解析Sql信息
    public $sqlInfo; //执行sql记录

    public $linkId;
    public $result;
    public $querystring;
    public $isclose;
    public $safecheck;

    public $table;
    public $join;
    public $field;
    public $where;
    public $order;
    public $limit;
    public $group;
    public $total;
    public $excID; //插入ID
    public $_sql; //最后执行sql
    public $chilidSql; //子查询

    private function __construct($dbConfig = '')
    {
        if ($dbConfig) {
            $this->dbConfig = $dbConfig;
        } else {
            $this->dbConfig = config();
        }

        $dbConfig = $this->dbConfig['db_config'];

        foreach ($dbConfig as $key => $value) {
            if ($value['db_host'] == '' || $value['db_user'] == '' || $value['db_name'] == '') {
                throw new Exception('接数据库信息有误！请查看是否配置正确与完整');
            }

            $res[$key] = $this->openMysql($value);

            Mysqli::$options['dbConfig'][$key] = $value;

        }

        Mysqli::$link = $res;

    }

    //单例实例化 避免重复New暂用资源
    public static function getInstance($dbConfig = '')
    {
        if (is_null(self::$instance)) {
            self::$instance = new Mysqli($dbConfig);
        }

        return self::$instance;

    }

    /**
     * 连接数据库
     * @date   2017-03-19T16:18:28+0800
     * @author ChenMingjiang
     */
    public function openMysql($dbConfig = array())
    {
        try {
            $res = mysqli_connect($dbConfig['db_host'], $dbConfig['db_user'], $dbConfig['db_pwd'], $dbConfig['db_name']);

            mysqli_query($res, 'set names utf8mb4');
            mysqli_query($res, 'SET sql_mode =\'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION\'');

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

        if (!$res) {
            throw new Exception('连接数据库失败，可能数据库密码不对或数据库服务器出错！');
        }

        return $res;
    }

    public function getSql()
    {
        return $this->_sql;
    }

    /** 链接 */
    public function connect($id = '')
    {

        $this->linkId   = !$id ? reset(Mysqli::$link) : Mysqli::$link[$id];
        $dbConfig       = !$id ? reset(Mysqli::$options['dbConfig']) : Mysqli::$options['dbConfig'][$id];
        $this->tablepre = $dbConfig['db_prefix'];

        if (!$this->linkId) {
            throw new Exception('链接信息异常');
        }

        $this->build['database'] = $dbConfig['db_name'];
        $this->build['content']  = !$id ? key(Mysqli::$link) : $id;

        return $this;
    }

    /** 构造Sql初始化 */
    public function init()
    {

        $this->where     = '';
        $this->field     = '*';
        $this->limit     = '';
        $this->group     = '';
        $this->order     = '';
        $this->join      = '';
        $this->chilidSql = false;
        $this->having    = '';
        $this->connect();
    }

    /**
     * 数据表
     * @date   2017-03-19T16:18:23+0800
     * @author ChenMingjiang
     * @param  [type]                   $table  [description]
     * @param  string                   $table2 [description]
     * @return [type]                           [description]
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
        if ($isTablepre) {
            $this->table = $this->tablepre != '' ? $this->table = $this->tablepre . $this->table : '';
        }

        $this->build['table'] = array('table' => $this->table, 'is_tablepre' => $isTablepre);

        return $this;
    }

    /**
     * 获取表名称
     * @date   2017-06-10T22:56:01+0800
     * @author ChenMingjiang
     * @param  [type]                   $table [description]
     * @return [type]                          [description]
     */
    public function tableName()
    {
        return $this->table;
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
        return $this->table;
    }

    /**
     * 判断是否存在该表
     * @date   2017-09-20T09:57:21+0800
     * @author ChenMingjiang
     * @return boolean                  [description]
     */
    public function isTable()
    {
        $this->_sql = 'SHOW TABLES LIKE \'dh_banner\'';
        $result     = (bool) mysqli_num_rows($this->query());
        return $result;
    }

    /**
     * 查询条件
     * @date   2017-03-19T16:18:18+0800
     * @author ChenMingjiang
     * @param  [type]                   $where [description]
     * @return [type]                          [description]
     */
    public function where($where, $value = null)
    {

        if (!$where) {
            return $this;
        }

        $newWhere = '';
        if ($value !== null && !is_array($where)) {
            $newWhere = $where . ' = \'' . $value . '\' AND ';
        } else {
            if (is_array($where)) {
                foreach ($where as $k => $v) {

                    if (strripos($k, '`') === false && $k != '_string') {
                        $k = strripos($k, '.') !== false ? str_replace('.', '.`', $k) . '`' : '`' . $k . '`';
                    }

                    if (is_array($v)) {
                        if ($v[0] == '>' || $v[0] == '<' || $v[0] == '>=' || $v[0] == '<=' || $v[0] == '!=' || $v[0] == 'like') {
                            $newWhere .= $k . '  ' . $v[0] . ' \'' . $v[1] . '\' AND ';
                        } elseif ($v[0] == 'in' || $v['0'] == 'not in') {
                            if (!$v[1]) {
                                $newWhere .= $k . '  ' . $v[0] . ' (\'\') AND ';
                            } else {

                                if (stripos($v[1], ',') !== false && !is_array($v[1])) {
                                    $v[1] = explode(',', $v[1]);
                                }

                                $v[1] = is_array($v[1]) ? $v[1] : (array) $v[1];

                                $commonInValue = '';
                                foreach ($v[1] as $inValue) {

                                    $commonInValue .= '\'' . $inValue . '\',';
                                }

                                $commonInValue = substr($commonInValue, 0, -1);

                                $newWhere .= $k . '  ' . $v[0] . ' (' . $commonInValue . ') AND ';
                            }
                        } elseif ($v[0] == 'instr') {
                            $newWhere .= $v[0] . '(' . $k . ',\'' . $v[1] . '\') AND ';
                        } elseif ($v[0] == 'between') {
                            $newWhere .= $k . '  ' . $v[0] . ' \'' . $v[1] . '\' AND \'' . $v[2] . '\' AND ';
                        } elseif ($v[0] == 'or') {
                            $newWhere .= $k . ' = \'' . $v[1] . '\' OR ';
                        }
                    } elseif ($k == '_string') {
                        $newWhere .= $v . ' AND ';
                    } else {
                        $newWhere .= $k . ' = \'' . $v . '\' AND ';
                    }
                }
            } else {
                $newWhere = $where;
            }
        }

        if (stripos($this->where, 'WHERE') === false) {
            $this->where = ' WHERE ' . substr($newWhere, 0, -4);
        } else {
            $this->where .= ' AND ' . substr($newWhere, 0, -4);
        }

        $this->build['where'] = $where;

        return $this;
    }

    /**
     * 关联查询
     * @date   2017-06-10T22:52:34+0800
     * @author ChenMingjiang
     * @param  [type]                   $table [description]
     * @param  [type]                   $where [description]
     * @param  string                   $float [description]
     * @return [type]                          [description]
     */
    public function join($table, $where = '', $float = 'left')
    {
        if ($table == $this->table) {
            denha\Log::error('表与关联表名字相同');
        }

        $where ?: $where = $this->table . '.id =' . $table . '.id';

        $this->join .= ' ' . $float . ' JOIN ' . $table . ' ON ' . $where;

        $this->build['join'] = array('table' => $table, 'where' => $where, 'float' => $float);

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
        $this->limit = ' LIMIT ' . $limit;
        if ($pageSize) {
            $this->limit = ' LIMIT ' . $limit . ',' . $pageSize;
        }

        $this->build['limit'] = array('limit' => $limit, 'pageSize' => $pageSize);

        return $this;
    }

    /**
     * 查询字段
     * @date   2017-03-19T16:18:09+0800
     * @author ChenMingjiang
     * @param  [type]                   $field [description]
     * @return [type]                          [description]
     */
    public function field($field = '*')
    {
        if (!$field) {
            $this->field = '*';
            return $this;
        }

        $newField = '';
        $field    = is_array($field) ? $field : explode(',', $field);
        foreach ($field as $k => $v) {
            if (stripos($v, 'as') === false && stripos($v, '*') === false && stripos($v, '`') === false && stripos($v, '.') === false && stripos($v, 'concat') === false) {
                $newField .= '`' . $v . '`,';
            } else {
                $newField .= $v . ',';
            }

        }

        $newField = substr($newField, 0, -1);

        $this->field = $newField;

        $this->build['field'] = $field;

        return $this;
    }

    public function group($value = '')
    {
        if (is_array($value)) {
            $i = 0;
            foreach ($value as $k => $v) {
                if ($i == 0) {
                    $newGroup .= $v;
                } else {
                    $newGroup .= "," . $v;
                }

                $i++;
            }
        } else {
            $newGroup = $value;
        }
        $this->group = ' GROUP BY ' . $newGroup;

        $this->build['group'] = $group;

        return $this;
    }

    public function order($value)
    {
        if (is_array($value)) {
            $i = 0;
            foreach ($value as $k => $v) {
                if ($i == 0) {
                    $newValue .= $v;
                } else {
                    $newValue .= "," . $v;
                }

                $i++;
            }
        } else {
            $newValue = $value;
        }

        if ($newValue) {
            $this->order = ' ORDER BY ' . $newValue;
        }

        $this->build['order'] = $this->order;

        return $this;
    }

    /**
     * 判断是否存在该数据表
     * @date   2017-03-19T16:18:01+0800
     * @author ChenMingjiang
     * @param  string                   $table [description]
     * @return [type]                          [description]
     */
    public function existsTbale($table = '')
    {
        if ($table == '') {$table = $this->table;}
        $sql                      = "SELECT COUNT(*) as total  FROM information_schema.TABLES WHERE TABLE_NAME='$table'";
        $t                        = mysqli_fetch_array(mysqli_query($this->linkId, $sql));
        if ($t['total'] == 0) {return false;}
        return true;
    }

    /** 查询数据表信息 */
    public function fieldStatus($field)
    {

        $fieldArray = stripos($field, ',') !== false ? explode(',', $field) : (array) $field;

        $this->_sql = "SHOW TABLE STATUS WHERE NAME = '{$this->table}'";
        $result     = $this->query();

        $this->total = mysqli_num_rows($result);
        if ($this->total == 0) {return false;}

        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
            $dataRow = $row;
        }

        if ($fieldArray) {
            foreach ($fieldArray as $key => $value) {
                if (isset($dataRow[$value])) {
                    $data[$value] = $dataRow[$value];
                }
            }
        } else {
            $data = $dataRow;
        }

        return $data;
    }

    /**
     * 查询表字段名
     * @date   2017-03-19T16:14:45+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function getField($field = 'column_name')
    {
        $this->where = ' where table_name = ' . "'" . $this->table . "'";
        $this->field = $field;
        $this->table = 'information_schema.columns';

        $this->_sql = "select " . $this->field . " from " . $this->table . $this->where;
        $result     = $this->query();

        while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
            if (stripos($field, ',') === false) {
                $data[] = $row['column_name'];
            } else {
                $data[] = $row;
            }

        }

        return $data;
    }

    /**
     * 统计总数
     * @date   2017-06-14T11:09:55+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function count($field = '')
    {

        $this->limit(1);
        if ($field) {
            $sql = 'SELECT   ' . $field . '  FROM ' . $this->table;
        } else {
            $sql = 'SELECT  COUNT(*) AS  t  FROM ' . $this->table;
        }

        if ($this->join) {
            $sql .= $this->join;
        }

        if ($this->where != '') {
            $sql .= $this->where;
        }
        if ($this->group != '') {
            $sql .= $this->group;
        }

        $result      = $this->query($sql);
        $this->total = mysqli_num_rows($result);

        if ($field) {
            return (int) $this->total;
        }

        $data = mysqli_fetch_array($result, MYSQLI_NUM);

        return $data[0];
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
        $this->chilidSql = $value;

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

    /**
     * hvaing
     * @date   2017-11-22T01:18:55+0800
     * @author ChenMingjiang
     * @param  [type]                   $field [description]
     * @return [type]                          [description]
     */
    public function having($field)
    {
        $this->having = ' HAVING ' . $field;
        return $this;
    }

    /**
     * 获取单个字段内容
     * @date   2018-04-06T21:35:17+0800
     * @author ChenMingjiang
     * @param  [type]                   $field [description]
     * @return [type]                          [description]
     */
    public function value($field)
    {
        if (!$this->table) {
            throw new Exception('请选择数据表');
        }

        $this->limit(1);

        $this->field($field);

        $this->_sql = 'SELECT ' . $this->field . ' FROM ' . $this->table;

        empty($this->join) ?: $this->_sql .= $this->join;
        empty($this->where) ?: $this->_sql .= $this->where;
        empty($this->group) ?: $this->_sql .= $this->group;
        empty($this->having) ?: $this->_sql .= $this->having;
        empty($this->order) ?: $this->_sql .= $this->order;
        empty($this->limit) ?: $this->_sql .= $this->limit;

        $result = $this->query();
        if (!$result) {
            throw new Exception('查询sql错误:' . $this->_sql);
        }

        //获取记录条数
        $this->total = mysqli_num_rows($result);
        if ($this->total == 0) {return false;}

        while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
            $this->field = str_replace('`', '', $this->field);

            $data = $row[0];
        }

        if (empty($data)) {
            return false;
        }

        return $data;
    }

    /**
     * 查询单条/多条信息
     * @date   2017-11-22T00:35:19+0800
     * @author ChenMingjiang
     * @param  string                   $value   [array:查询数据 one:查询单条单个字段内容]
     * @param  boolean                  $isArray [单字段 数组模式]
     * @param  boolean                  $chilid  [子查询]
     * @return [type]                            [description]
     */
    public function find($value = '', $isArray = false)
    {

        if (!$this->table) {
            throw new Exception('请选择数据表');
        }

        if (!$this->limit && $value != 'array' && !$isArray) {
            $this->limit(1);
        }

        $this->_sql = 'SELECT ' . $this->field . ' FROM ' . $this->table;

        empty($this->join) ?: $this->_sql .= $this->join;
        empty($this->where) ?: $this->_sql .= $this->where;
        empty($this->group) ?: $this->_sql .= $this->group;
        empty($this->having) ?: $this->_sql .= $this->having;
        empty($this->order) ?: $this->_sql .= $this->order;
        empty($this->limit) ?: $this->_sql .= $this->limit;

        //开启子查询直接返回sql
        if ($this->chilidSql) {
            return $this->_sql;
        }

        $result = $this->query();
        if (!$result) {
            throw new Exception('查询sql错误:' . $this->_sql);
        }

        //获取记录条数
        $this->total = mysqli_num_rows($result);
        if ($this->total == 0) {
            if ($value == 'array') {
                return array();
            } elseif ($value == 'one') {
                return null;
            } else {
                return '';
            }
        }

        //单个字段模式
        if ($value == 'one' && !$isArray) {
            $row = mysqli_fetch_array($result, MYSQLI_NUM);
            if (empty($row)) {
                return false;
            }

            return $row[0];
        }
        //单字段数组模式
        elseif ($value == 'one' && $isArray) {
            while ($row = mysqli_fetch_array($result, MYSQLI_NUM)) {
                $this->field = str_replace('`', '', $this->field);

                $isArray !== true ? $data[$row[1]] = $row[0] : $data[] = $row[0];
            }

            if (empty($data)) {
                return false;
            }
            return $data;
        }
        //三维数组模式
        elseif ($this->total > 1 || $value == 'array') {

            while ($row = mysqli_fetch_array($result, MYSQLI_ASSOC)) {
                $data[] = $row;
            }

            for ($i = 0, $n = count($data); $i < $n; $i++) {
                if (is_array($data[$i])) {
                    foreach ($data[$i] as $key => $value) {
                        $datas[$i][$key] = $value;
                    }
                }
            }

            return $datas;
        }
        //二维数组模式
        else {
            $row = mysqli_fetch_array($result, MYSQLI_ASSOC);

            foreach ($row as $key => $value) {
                $data[$key] = $value;
            }

            return $data;
        }
    }

    public function select()
    {

    }

    /**
     * 添加
     * @date   2017-03-19T16:19:43+0800
     * @author ChenMingjiang
     * @param  string                   $data [description]
     */
    public function add($data = '')
    {
        $newField = '';
        $data     = is_array($data) ? $data : explode(',', $data);

        foreach ($data as $k => $v) {
            $v = str_replace('\'', '\\\'', $v);
            $v = str_replace('\\', '\\/\\', $v);
            $newField .= '`' . $k . '` = \'' . $v . '\',';
        }

        $newField    = substr($newField, 0, -1);
        $this->field = $newField;

        $this->_sql = 'INSERT INTO `' . $this->table . '` SET ' . $this->field;
        $result     = $this->query();
        if ($result) {
            $result = max(mysqli_insert_id($this->linkId), 1);
        }
        return $result;
    }

    /**
     * 添加多条信息
     * @date   2017-09-19T15:45:40+0800
     * @author ChenMingjiang
     */
    public function addAll($data = array())
    {
        foreach ($data as $key => $value) {
            $result = $this->add($value);
        }
        return $result;
    }

    /**
     * 修改保存
     * @date   2017-03-19T16:20:24+0800
     * @author ChenMingjiang
     * @param  string                   $data [description]
     * @return [type]                         [description]
     */
    public function save($data = '', $value = null)
    {
        if (!$this->where) {
            return false;
        }

        $newField = '';
        if ($value !== null && !is_array($data)) {
            $value    = str_replace('\\', '\\\\', $value);
            $value    = str_replace('\'', '\\\'', $value);
            $newField = '`' . $data . '`=\'' . $value . '\'';
        } else {
            if (is_array($data)) {
                foreach ($data as $k => $v) {
                    if (is_array($v)) {
                        $v[0] = strtolower($v[0]);
                        if ($v[0] == 'add') {
                            $newField .= '`' . $k . '`  = `' . $k . '` + ' . $v[1] . ',';
                        } elseif ($v[0] == 'less') {
                            $newField .= '`' . $k . '`  = `' . $k . '` - ' . $v[1] . ',';
                        } elseif ($v[0] == 'concat') {
                            $newField .= '`' . $k . '`  = CONCAT(`' . $k . '`,\'\',\'' . str_replace('\'', '\\\'', $v[1]) . '\'),';
                        }
                    } else {
                        $v = str_replace('\\', '\\\\', $v);
                        $v = str_replace('\'', '\\\'', $v);
                        $newField .= '`' . $k . '`=\'' . $v . '\',';
                    }
                }
                $newField = substr($newField, 0, -1);
            } else {
                $newField = $field;
            }
        }

        $this->field = $newField;

        $this->_sql = 'UPDATE ' . $this->table . ' SET ' . $this->field;
        $this->_sql .= $this->where ? $this->where : '';

        $result = $this->query();
        return $result;
    }

    /**
     * 删除数据
     * @date   2017-03-19T16:20:32+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function delete()
    {
        if (!$this->where) {
            return false;
        }

        $this->_sql = 'DELETE FROM ' . $this->table . $this->where;
        $result     = $this->query();
        return $result;
    }

    //开启事务
    public function startTrans()
    {
        mysqli_query($this->linkId, 'begin');
        /*$this->query('begin');*/
        return true;
    }

    //回滚事务
    public function rollback()
    {
        mysqli_query($this->linkId, 'rollback');
        /*$this->query('rollback');*/
        return true;
    }

    //提交事务
    public function commit()
    {
        mysqli_query($this->linkId, 'commit');
        /* $this->query('commit');*/
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
        !$sql ?: $this->_sql = $sql;
        $_beginTime          = microtime(true);
        $result              = mysqli_query($this->linkId, $this->_sql);
        $_endTime            = microtime(true);

        $this->sqlInfo['time'] = $_endTime - $_beginTime; //获取执行时间
        $this->sqlInfo['sql']  = $this->_sql;

        if ($result) {
            Trace::addSqlInfo($this->sqlInfo); //存入调试信息中
            $this->addSqlLog(); //存入文件中
            return $result;
        } else {
            Trace::addErrorInfo('[SQL ERROR] ' . $this->_sql);
            $this->addErrorSqlLog(); //存入文件
            return false;
        }

    }

    public function addErrorSqlLog()
    {
        //如果没有写入权限尝试修改权限 如果修改后还是失败 则跳过
        if (isWritable(DATA_PATH)) {
            $path = DATA_PATH . 'sql_log' . DS . $this->build['database'] . DS;
            is_dir($path) ? '' : mkdir($path, 0755, true);
            $path .= 'error_' . date('Y_m_d_H', TIME) . '.text';

            $time = &$this->sqlInfo['time'];
            $info = '------ ' . $time . ' | ' . date('Y-m-d H:i:s', TIME) . ' | ip:' . getIP() . ' | ';
            $info .= 'Url:' . URL . ' | Controller:' . CONTROLLER . ' | Action:' . ACTION . PHP_EOL;

            $content = $this->sqlInfo['sql'] . ';' . PHP_EOL . '来源：' . getSystem() . getBrowser() . PHP_EOL . '--------------' . PHP_EOL;
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

        $time = &$this->sqlInfo['time'];
        $info = '------ ' . $time . ' | ' . date('Y-m-d H:i:s', TIME) . ' | ip:' . getIP() . ' | ';
        $info .= 'Url:' . URL . ' | Controller:' . CONTROLLER . ' | Action:' . ACTION . PHP_EOL;

        //记录sql
        if ($this->sqlInfo && $this->dbConfig['db_save_log']) {
            $path = DATA_PATH . 'sql_log' . DS . $this->build['database'] . DS;
            is_dir($path) ? '' : mkdir($path, 0755, true);
            if (stripos($this->sqlInfo['sql'], 'select') === 0) {
                $path .= 'select_' . date('Y_m_d_H', TIME) . '.text';
                $content = $this->sqlInfo['sql'] . PHP_EOL;
            } elseif (stripos($this->sqlInfo['sql'], 'update') === 0) {
                $path .= 'update_' . date('Y_m_d_H', TIME) . '.text';
                $content = $this->sqlInfo['sql'] . ';' . PHP_EOL;
            } elseif (stripos($this->sqlInfo['sql'], 'delete') === 0) {
                $path .= 'delete_' . date('Y_m_d_H', TIME) . '.text';
                $content = $this->sqlInfo['sql'] . ';' . PHP_EOL;
            } elseif (stripos($this->sqlInfo['sql'], 'insert') === 0) {
                $path .= 'add_' . date('Y_m_d_H', TIME) . '.text';
                $content = $this->sqlInfo['sql'] . ';' . PHP_EOL;
            }

            //记录慢sql
            if ($this->dbConfig['db_slow_save_log']) {
                if ($this->sqlInfo['time'] > $this->dbConfig['db_slow_time']) {
                    $path .= 'slow_' . date('Y_m_d_H', TIME) . '.text';
                    $content = $this->sqlInfo['sql'] . PHP_EOL;
                }
            }

            $file = fopen($path, 'a');
            fwrite($file, $content . $info . PHP_EOL);
            fclose($file);
        }
    }
}
