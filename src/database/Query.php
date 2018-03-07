<?php
/** sql生成 */
namespace denha\database;

class Query
{
    // 数据库Connection对象实例
    protected $connection;
    // 数据库Builder对象实例
    protected $builder;
    // 查询参数
    protected $options = [];
    // 参数绑定
    protected $bind = [];
    // 数据表信息
    protected static $info = [];
    // 回调事件
    private static $event = [];

    /**
     * 构造函数
     * @access public
     * @param Connection $connection 数据库对象实例
     * @param string     $model      模型名
     */
    public function __construct(Connection $connection = null, $model = '')
    {
        //$this->connection = $connection ?: Db::connect([], true);
        //$this->prefix = $this->connection->getConfig('prefix');
        //$this->model      = $model;
        // 设置当前连接的Builder对象
        //$this->setBuilder();
    }

    // /**
    //  * 利用__call方法实现一些特殊的Model方法
    //  * @access public
    //  * @param string $method 方法名称
    //  * @param array  $args   调用参数
    //  * @return mixed
    //  * @throws DbException
    //  * @throws Exception
    //  */
    // public function __call($method, $args)
    // {
    //     var_dump($method);
    //     var_dump($args);die;
    // }

    public function table($name)
    {
        $this->options['table'] = $this->parseTable($name);
        return $this;
    }

    public function where($data, $value, $condition)
    {

        $this->options['where'] = $this->parseWhere($data, $value, $condition);
        return $this;
    }

    public function whereMap($where)
    {
        $this->options['where'] = $this->parseWhereMap($where);
        return $this;
    }

    public function field($data, $options)
    {
        return $this;
    }

    public function select()
    {
        $data = $this->bulidSelect();
        return $data;
    }

    public function selectRow()
    {
        return $data;
    }
}
