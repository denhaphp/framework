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
            $type = isset(self::$traceErrorType[$level]) ? self::$traceErrorType[$level] : 'unknown';
            $info = $type . ' : ' . $message . ' from ' . $file . ' on ' . $line;
            self::addErrorInfo($info);

            $e = array(
                'message' => $type . ' : ' . $message,
                'file'    => $file,
                'line'    => $line,
            );

            return include FARM_PATH . DS . 'trace' . DS . 'error.html';
        }
    }

    //捕获致命错误信息 并显示
    public static function catchError()
    {
        $e = error_get_last();
        if ($e) {
            if (config('trace')) {
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

        if ($error->getTrace()) {
            foreach ($error->getTrace() as $key => $value) {
                $e['trace'] .= ($key + 1) . ' File :' . $value['file'];
                if ($value['class']) {
                    $e['trace'] .= ' From Class :' . $value['class'];
                }

                if ($value['class']) {
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

    //获取基本信息
    private static function baseInfo()
    {

        $config = config();
        $base   = array(
            '请求信息' => date('Y-m-d H:i:s', $_SERVER['REQUEST_TIME']) . ' ' . $_SERVER['SERVER_PROTOCOL'] . ' ' . $_SERVER['REQUEST_METHOD'] . ' : ' . strip_tags($_SERVER['REQUEST_URI']),
            '运行时间' => (microtime(true)) - $GLOBALS['_beginTime'] . ' s',
            '吞吐率'    => number_format(1 / $GLOBALS['_beginTime'], 2) . 'req/s',
            '内存开销' => MEMORY_LIMIT_ON ? number_format((memory_get_usage() - $GLOBALS['_startUseMems']) / 1024, 2) . ' kb' : '不支持',
            //'查询信息' => n('db_query') . ' queries ' . n('db_write') . ' writes ',
            '文件加载' => count(get_included_files()),
            //'缓存信息' => n('cache_read') . ' gets ' . n('cache_write') . ' writes ',
            //'配置加载' => count(c()),
            '会话信息' => 'SESSION_ID=' . session_id(),
            '数据库'    => $config['db_config'][0]['db_name'],
        );

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

    //获取执行时间
    private static function showTime()
    {
        /* useup('beginTime', $GLOBALS['_beginTime']);
    useup('viewEndTime');
    return useup('beginTime', 'viewEndTime') . 's ( Load:' . useup('beginTime', 'loadTime') . 's Init:' . useup('loadTime', 'initTime') . 's Exec:' . useup('initTime', 'viewStartTime') . 's Template:' . useup('viewStartTime', 'viewEndTime') . 's )';*/
    }
}
