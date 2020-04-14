<?php
//------------------------
//· Sqlite数据库操作类
//---------------------
namespace denha\db\handler;

use denha;
use denha\db\Container;

class Sqlite extends Container
{
    protected function parseDsn(array $config)
    {
        $dsn = 'sqlite:' . $config['database'];

        return $dns;
    }

    protected function explain()
    {
        return [];
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

        $sql = 'PRAGMA table_info( ' . $this->bulid['field'] . ' )';

        $result = $this->query($sql);
        $list   = $result->fetchAll(PDO::FETCH_ASSOC);

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
