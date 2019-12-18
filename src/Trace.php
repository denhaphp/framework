<?php
//------------------------
//· 调试信息函数
//-------------------------
namespace denha;

use denha\Config;
use denha\Start;

class Trace
{
    private static $tracePageTabs  = ['BASE' => '基本', 'FILE' => '文件', 'ERR|NOTIC' => '错误', 'SQL' => 'SQL', 'DEBUG' => '调试'];
    private static $traceErrorType = [0 => '', 1 => 'FATAL ERROR', 2 => 'WARNING', 4 => 'PARSE', 8 => 'NOTICE', 100 => 'SQL'];
    private static $dbTrace        = []; // 数据库调试信息

    public static $errorInfo = []; //错误信息
    public static $sqlInfo   = []; //sql执行信息

    //执行
    public static function run()
    {
        echo self::showTrace();
    }

    //展示调试信息
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
                case 'ERR|NOTIC':
                    $trace[$title] = self::$errorInfo;
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

    //捕获Notice错误信息
    public static function catchNotice($level, $message, $file, $line)
    {

        if ($level) {

            $debugContent = self::getFileContent($file, $line);

            $type = isset(self::$traceErrorType[$level]) ? self::$traceErrorType[$level] : 'unknown';
            $info = $type . ' : ' . $message . ' from ' . $file . ' on ' . $line;
            self::addErrorInfo($info);

            if (Config::get('trace')) {
                $e = array(
                    'message' => $type . ' : ' . $message,
                    'file'    => $file,
                    'line'    => $line,
                );

                header('HTTP/1.1 500 Internal Server Error');
                return include FARM_PATH . DS . 'trace' . DS . 'error.html';
            }
        }
    }

    // 捕获致命错误信息 并显示
    public static function catchError()
    {

        $e = error_get_last();
        if ($e) {

            $debugContent = self::getFileContent($e['file'], $e['line']);

            if (Config::get('error_log')) {

                $path = DATA_RUN_PATH;
                is_dir($path) ? '' : mkdir($path, 0755, true);

                $path .= date('Y_m_d', TIME) . '.text';

                $info = '------ ' . 'FATAL ERROR : ' . $e['message'] . ' from ' . $e['file'] . ' on line ' . $e['line'];
                $info .= ' | ' . date('Y-m-d H:i:s', TIME);
                $info .= ' | ip:' . Config::IP();
                $info .= ' | Url:' . URL . '/' . Route::$uri;
                $info .= PHP_EOL;

                $content = $this->sqlInfo['sql'] . ';' . PHP_EOL . '--------------' . PHP_EOL;

                error_log($content . $info, 3, $path);

            }

            if (Config::get('debug')) {
                header('HTTP/1.1 500 Internal Server Error');
                return include FARM_PATH . DS . 'trace' . DS . 'error.html';
            } else {
                header("http/1.1 404 not found");
                header("status: 404 not found");
                return include FARM_PATH . DS . 'trace' . DS . '404.html';
            }
        }
    }

    //捕获未处理的自定义错误信息 并显示
    public static function catchApp($error)
    {

        $e['type']    = 0;
        $e['message'] = $error->getMessage();
        $e['file']    = $error->getFile();
        $e['line']    = $error->getLine();
        $e['trace']   = '';

        $debugContent = self::getFileContent($e['file'], $e['line']);

        if ($error->getTrace()) {
            foreach ($error->getTrace() as $key => $value) {
                $e['trace'] .= ($key + 1) . ' File :' . (!empty($value['file']) ? $value['file'] : '未知地址');
                if (isset($value['class'])) {
                    $e['trace'] .= ' From Class :' . $value['class'];
                }

                if (isset($value['class'])) {
                    $e['trace'] .= ' In Function :' . $value['function'];
                }

                $e['trace'] .= ' on line ' . (!empty($value['line']) ? $value['line'] : '未知行数') . PHP_EOL;
            }
        }

        if (Config::get('trace')) {
            header('HTTP/1.1 500 Internal Server Error');
            return include FARM_PATH . DS . 'trace' . DS . 'error.html';
        } else {
            header("http/1.1 404 not found");
            header("status: 404 not found");
            return include FARM_PATH . DS . 'trace' . DS . '404.html';
        }
    }

    // 增加非致命错误信息记录
    public static function addErrorInfo($data)
    {

        if (!self::$errorInfo) {
            self::$errorInfo[] = $data;
        } else {
            self::$errorInfo = array_merge(self::$errorInfo, (array) $data);
        }
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

            if (isset($data['PS'])) {
                self::$dbTrace['psInfo'] = $data['PS'];

            } elseif (isset($data['explain'])) {
                $info[] = 'SQL :' . $data['sql'] . ' [' . $data['time'] . 's]';
                foreach ($data['explain'] as $explain) {
                    $info[] = 'EXPLAIN :' . json_encode($explain);
                }
            } else {
                $info[] = 'SQL :' . $data['sql'] . ' [' . $data['time'] . 's]';
            }

        } else {
            $info[] = $data;
        }

        if (isset($info)) {
            if (!self::$sqlInfo) {
                self::$sqlInfo = $info;
                if (isset($dbTraceInfo)) {
                    self::$sqlInfo[0] = isset(self::$dbTrace['psInfo']) ? self::$dbTrace['psInfo'] . ' ' . $dbTraceInfo : $dbTraceInfo;
                }
            } else {
                self::$sqlInfo = array_merge(self::$sqlInfo, (array) $info);
                if (isset($dbTraceInfo)) {
                    self::$sqlInfo[0] = isset(self::$dbTrace['psInfo']) ? self::$dbTrace['psInfo'] . ' ' . $dbTraceInfo : $dbTraceInfo;
                }
            }
        }

    }

    /** 获取文件代码 */
    private static function getFileContent($file, $line)
    {
        $content = '';

        if (is_file($file)) {

            $fp = new \SplFileObject($file, 'r');
            $fp->seek($i = max($line - 10, 0)); // 转到第N行, seek方法参数从0开始计数

            $count = $line + 10;
            for (; $i <= $count; ++$i) {
                $content .= $i . ' ' . trim($fp->current()) . PHP_EOL; // current()获取当前行内容
                if ($i == $line) {

                }

                $fp->next(); // 下一行
            }

            $content = '<pre>' . htmlspecialchars($content) . '</pre>';

        }

        return $content;
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
            '请求信息'          => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']) . ' ' . $_SERVER['SERVER_PROTOCOL'] . ' ' . $_SERVER['REQUEST_METHOD'] . ' : ' . strip_tags($_SERVER['REQUEST_URI']),
            '运行时间'          => number_format(microtime(true) - START_TIME, 6) . ' s',
            '吞吐率'             => number_format(1 / (microtime(true) - START_TIME), 2) . 'req/s',
            '内存开销'          => number_format((memory_get_usage() - START_USE_MENUS) / 1024, 2) . ' kb',
            '文件加载'          => count(get_included_files()),
            '配置加载'          => count(Start::$config),
            '会话信息'          => 'SESSION_ID=' . session_id(),

            '数据库运行时间' => isset(self::$dbTrace['allTime']) ? self::$dbTrace['allTime'] : 0,
            '数据库'             => $dbName,
            '磁盘信息'          => number_format(DISK_TOTAL_SPACE / 1024 / 1024 / 1024, 3) . ' G (all) / ' . number_format((DISK_TOTAL_SPACE - DISK_FREE_SPACE) / 1024 / 1024 / 1024, 3) . ' G (use) / ' . number_format(DISK_FREE_SPACE / 1024 / 1024 / 1024, 3) . 'G (free)',
        ];

        return $base;
    }

    //获取加载文件
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
