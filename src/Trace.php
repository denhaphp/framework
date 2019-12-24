<?php
//------------------------
//· 调试信息函数
//-------------------------

declare (strict_types = 1);

namespace denha;

use denha\Config;
use denha\Log;
use denha\Start;

class Trace
{
    private static $tracePageTabs = ['BASE' => '基本', 'FILE' => '文件', 'SQL' => 'SQL', 'DEBUG' => '调试'];
    private static $dbTrace       = []; // 数据库调试信息

    public static $errorInfo = []; //错误信息
    public static $sqlInfo   = []; //sql执行信息

    // 执行
    public static function run()
    {
        echo self::showTrace();
    }

    // 展示调试信息
    private static function showTrace()
    {
        $trace = [];
        $tabs  = self::$tracePageTabs;
        foreach ($tabs as $name => $title) {
            switch (strtoupper($name)) {
                case 'BASE':
                    $trace[$title] = self::baseInfo();
                    break;
                case 'FILE':
                    $trace[$title] = self::fileInfo();
                    break;
                case 'SQL':
                    $trace[$title] = self::$sqlInfo;
                    break;
                case 'DEBUG':
                    $trace[$title] = self::baseInfo();
                    break;
                default:
                    $trace[$title] = $name;
                    break;
            }
        }

        ob_start();
        include FARM_PATH . DS . 'trace' . DS . 'debug.html';
        return ob_get_clean();
    }

    // 增加sql执行信息记录
    public static function addSqlInfo($data)
    {
        if (is_array($data)) {

            if (isset($data['time'])) {

                self::$dbTrace['allTime'] = isset(self::$dbTrace['allTime']) ? self::$dbTrace['allTime'] : 0;
                self::$dbTrace['allTime'] += $data['time'];

                $dbTraceInfo = '数据库总运行时间:' . self::$dbTrace['allTime'];
            }

            if (isset($data['explain'])) {
                $info[] = 'SQL:' . $data['sql'] . ' [' . $data['time'] . 's]';
                foreach ($data['explain'] as $explain) {
                    $info[] = 'EXPLAIN :' . json_encode($explain);
                }
            } else {
                $info[] = 'SQL:' . $data['sql'] . ' [' . $data['time'] . 's]';
            }

        } else {
            $info[] = $data;
        }

        // 日志记录埋点
        if ($info) {
            foreach ($info as $message) {
                Log::info($message);
            }
        }

        if (isset($info)) {
            if (!self::$sqlInfo) {
                self::$sqlInfo = $info;
                if (isset($dbTraceInfo)) {
                    self::$sqlInfo[0] =  $dbTraceInfo;
                }
            } else {
                self::$sqlInfo = array_merge(self::$sqlInfo, (array) $info);
                if (isset($dbTraceInfo)) {
                    self::$sqlInfo[0] =  $dbTraceInfo;
                }
            }
        }

    }

    //获取基本信息
    private static function baseInfo()
    {

        $dbConfig = Config::includes('db.php')['config'];
        $dbName   = '';
        foreach ($dbConfig as $item) {
            !isset($item['host']) ?: $dbName .= $item['host'] . ' : ';
            !isset($item['name']) ?: $dbName .= $item['name'] . ':' . $item['port'] . '  / ';
        }

        $base = [
            '请求信息'    => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']) . ' ' . $_SERVER['SERVER_PROTOCOL'] . ' ' . $_SERVER['REQUEST_METHOD'] . ' : ' . strip_tags($_SERVER['REQUEST_URI']),
            '运行时间'    => number_format(microtime(true) - START_TIME, 6) . ' s',
            '吞吐率'     => number_format(1 / (microtime(true) - START_TIME), 2) . 'req/s',
            '内存开销'    => number_format((memory_get_usage() - START_USE_MENUS) / 1024, 2) . ' kb',
            '文件加载'    => count(get_included_files()),
            '配置加载'    => count(Start::$config),
            '会话信息'    => 'SESSION_ID=' . session_id(),

            '数据库运行时间' => isset(self::$dbTrace['allTime']) ? self::$dbTrace['allTime'] : 0,
            '数据库'     => $dbName,
            '磁盘信息'    => self::diskInfo(),
        ];

        return $base;
    }

    public static function diskInfo()
    {
        return number_format(disk_total_space(ROOT_PATH) / 1024 / 1024 / 1024, 3) . ' G (all) / ' . number_format((disk_total_space(ROOT_PATH) - disk_free_space(ROOT_PATH)) / 1024 / 1024 / 1024, 3) . ' G (use) / ' . number_format(disk_free_space(ROOT_PATH) / 1024 / 1024 / 1024, 3) . 'G (free)';
    }

    // 获取加载文件
    private static function fileInfo()
    {
        $files = get_included_files();
        $info  = [];

        foreach ($files as $key => $file) {
            $info[] = $file . ' ( ' . number_format(filesize($file) / 1024, 2) . ' KB )';
        }
        return $info;
    }

}
