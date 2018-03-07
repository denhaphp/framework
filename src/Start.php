<?php
namespace denha;

class Start
{
    public static $client;
    public static $config = array();

    /**
     * [start description]
     * @date   2017-07-14T16:12:51+0800
     * @author ChenMingjiang
     * @param  string                   $client [配置文件名称]
     * @param  string                   $route  [路由模式 mca smca ca]
     * @return [type]                           [description]
     */
    public static function up($route = 'mca')
    {
        //执行创建文件
        get('build') == false ?: self::bulid();

        self::$client = APP_CONFIG;
        //获取配置文档信息
        self::$config = include CONFIG_PATH . 'config.php';
        if (is_file(CONFIG_PATH . 'config.' . APP . '.php')) {
            self::$config = array_merge(include (CONFIG_PATH . 'config.php'), include (CONFIG_PATH . 'config.' . APP . '.php'));
        }

        error_reporting(0);
        register_shutdown_function('denha\Trace::catchError');
        set_error_handler('denha\Trace::catchNotice');
        set_exception_handler('denha\Trace::catchApp');

        Start::filter(); //过滤
        Route::main($route); //解析路由

        if (preg_match("/^[A-Za-z](\/|\w)*$/", CONTROLLER)) {
            $class = Route::$class;
            if (class_exists($class)) {
                $object = new $class();
            } else {
                $object = false;
            }
        } else {
            $object = false;
        }

        if (!$object) {
            throw new Exception('NOT FIND CONTROLLER : ' . CONTROLLER . ' in ' . $class = Route::$class);
        }

        $action = lcfirst(parsename(ACTION, 1));

        //如果是POST提交 并且存在 function xxxPost方法 则自动调用该方法
        !(IS_POST && method_exists($object, $action . 'Post')) ?: $action .= 'Post';

        if (!method_exists($object, $action)) {
            throw new Exception('Class : ' . Route::$class . ' NOT FIND [ ' . $action . ' ] ACTION');
        }

        //待开发 自动生成api接口文档
        //get('api') == false ?: self::apiDoc(Route::$class, $action);
        $action = $object->$action();
    }

}
