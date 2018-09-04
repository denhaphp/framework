<?php
//------------------------
// 调试信息函数
//-------------------------
namespace denha;

class Trace
{
    private static $tracePageTabs  = ['BASE' => '基本', 'FILE' => '文件', 'ERR|NOTIC' => '错误', 'SQL' => 'SQL', 'DEBUG' => '调试'];
    private static $traceErrorType = [0 => '', 1 => 'FATAL ERROR', 2 => 'WARNING', 4 => 'PARSE', 8 => 'NOTICE', 100 => 'SQL'];

    public static $errorInfo = array(); //错误信息
    public static $sqlInfo   = array(); //sql执行信息

    //执行
    public static function run()
    {
        echo self::showTrace();
    }

    //展示调试信息
    private static function showTrace()
    {
        $trace = array();
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

            if (config('trace')) {
                $e = array(
                    'message' => $type . ' : ' . $message,
                    'file'    => $file,
                    'line'    => $line,
                );

                return include FARM_PATH . DS . 'trace' . DS . 'error.html';
            }
        }
    }

    //捕获致命错误信息 并显示
    public static function catchError()
    {
        $e = error_get_last();
        if ($e) {

            $debugContent = self::getFileContent($e['file'], $e['line']);

            if (config('debug')) {
                return include FARM_PATH . DS . 'trace' . DS . 'error.html';
            } else {
                //FATAL ERROR 发送到邮箱
                if (config('send_debug_mail')) {
                    $title   = $_SERVER['HTTP_HOST'] . ' 有一个致命错误 ip:' . getIP() . ' ' . $_SERVER['SERVER_PROTOCOL'];
                    $content = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . PHP_EOL . 'FATAL ERROR : ' . $e['message'] . ' from ' . $e['file'] . ' on line ' . $e['line'];
                    dao('Mail')->send(config('send_mail'), $title, $content);
                }

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
                $e['trace'] .= ($key + 1) . ' File :' . $value['file'];
                if (isset($value['class'])) {
                    $e['trace'] .= ' From Class :' . $value['class'];
                }

                if (isset($value['class'])) {
                    $e['trace'] .= ' In Function :' . $value['function'];
                }

                $e['trace'] .= ' on line ' . $value['line'] . PHP_EOL;
            }
        }

        if (config('trace')) {
            return include FARM_PATH . DS . 'trace' . DS . 'error.html';
        } else {
            header("http/1.1 404 not found");
            header("status: 404 not found");
            return include FARM_PATH . DS . 'trace' . DS . '404.html';
        }
    }

    //增加非致命错误信息记录
    public static function addErrorInfo($data)
    {

        if (!self::$errorInfo) {
            self::$errorInfo[] = $data;
        } else {
            self::$errorInfo = array_merge(self::$errorInfo, (array) $data);
        }
    }

    //增加sql执行信息记录
    public static function addSqlInfo($data)
    {
        if (is_array($data)) {
            $info = 'SQL :' . $data['sql'] . ' [' . $data['time'] . 's]';
        } else {
            $info = $data;
        }

        if (!self::$sqlInfo) {
            self::$sqlInfo[] = $info;
        } else {
            self::$sqlInfo = array_merge(self::$sqlInfo, (array) $info);
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

        $dbConfig = config('dbConfig', 'db');
        $dbName   = '';
        foreach ($dbConfig as $key => $value) {
            !isset($value['host']) ?: $dbName .= $value['host'] . ' : ';
            !isset($value['name']) ?: $dbName .= $value['name'] . '  / ';
        }

        $base = [
            '请求信息' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']) . ' ' . $_SERVER['SERVER_PROTOCOL'] . ' ' . $_SERVER['REQUEST_METHOD'] . ' : ' . strip_tags($_SERVER['REQUEST_URI']),
            '运行时间' => number_format(microtime(true) - START_TIME, 6) . ' s',
            '吞吐率'    => number_format(1 / (microtime(true) - START_TIME), 2) . 'req/s',
            '内存开销' => number_format((memory_get_usage() - START_USE_MENUS) / 1024, 2) . ' kb',
            '文件加载' => count(get_included_files()),
            //'缓存信息' => n('cache_read') . ' gets ' . n('cache_write') . ' writes ',
            '配置加载' => count(config()),
            '会话信息' => 'SESSION_ID=' . session_id(),
            '数据库'    => $dbName,
            '磁盘信息' => number_format(DISK_TOTAL_SPACE / 1024 / 1024 / 1024, 3) . ' G (all) / ' . number_format((DISK_TOTAL_SPACE - DISK_FREE_SPACE) / 1024 / 1024 / 1024, 3) . ' G (use) / ' . number_format(DISK_FREE_SPACE / 1024 / 1024 / 1024, 3) . 'G (free)',
        ];

        return $base;
    }

    //获取加载文件
    private static function fileInfo()
    {
        $files = get_included_files();
        $info  = array();

        foreach ($files as $key => $file) {
            $info[] = $file . ' ( ' . number_format(filesize($file) / 1024, 2) . ' KB )';
        }
        return $info;
    }

}
