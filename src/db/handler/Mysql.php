<?php
//------------------------
//· Mysql数据库操作类
//---------------------
namespace denha\db\handler;

use denha;
use denha\db\Container;
use \PDO;

class Mysql extends Container
{
    protected function parseDsn(array $config)
    {
        $dns = 'mysql:host=' . $config['host'] . ':' . $config['port'] . ';dbname=' . $config['name'] . ';charset=' . $config['charset'];

        return $dns;
    }

    protected function explain()
    {

        $statement = $this->link->prepare('explain ' . $this->bulid['sql']);

        if (isset($this->bulid['params']) && $this->bulid['params']) {
            foreach ($this->bulid['params'] as $bulids) {
                foreach ($bulids as $item) {
                    $statement->bindParam(...$this->buildParam($item));

                }
            }
        }
        $res = $statement->execute();

        if ($res) {
            $this->info['explain'] = $statement->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    /** 查询表字段名 */
    public function getField($field = 'COLUMN_NAME')
    {
        // 执行获取缓存数据
        if ($result = $this->getCache(__FUNCTION__)) {
            return $result;
        }

        $this->connect();
        $this->parseTable();

        $this->field($field);
        $this->parseField();

        $where = ' WHERE table_name = \'' . $this->bulid['table'] . '\'';
        if (isset($this->options['group']) && $this->options['group']) {
            $where .= $this->parseGroup();
        }

        $this->bulid['sql'] = 'SELECT ' . $this->bulid['field'] . ' from information_schema.columns ' . $where;
        $result             = $this->query($this->bulid['sql']);
        $list               = $result->fetchAll(PDO::FETCH_ASSOC);

        if (count($this->options['field']) == 1) {
            foreach ($list as $key => $value) {
                $data[] = $value[$field];
            }
        } else {
            $data = $list;
        }

        // 如果开启缓存则保存缓存
        return $this->setCache($data);
    }
}
