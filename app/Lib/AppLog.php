<?php

namespace App\Lib;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

/**
 * user defined Log class, with StreamHandler and LineFormatter
 *
 * @author Sphenginx
 */
class AppLog
{

    //define static log instance.
    protected static $_log_instance;

    /**
     * 获取log实例
     *
     * @return obj
     * @author Sphenginx
     **/
    public static function getLogInstance()
    {
        if (static::$_log_instance === null) {
            static::$_log_instance = new Logger('openplatform');
        }
        return static::$_log_instance;
    }

    /**
     * Handle dynamic, static calls to the object.
     *
     * @param  string  $method 可用方法: debug|info|notice|warning|error|critical|alert|emergency 可调用的方法详见 Monolog\Logger 类
     * @param  array   $args 调用参数
     * @return mixed
     * @author Sphenginx
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getLogInstance();

        //组织参数信息
        $message = $args[0];
        $context = isset($args[1]) ? $args[1] : [];
        $path    = isset($args[2]) ? $args[2] : 'api/';

        //设置日志处理手柄，默认为写入文件（还有mail、console、db、redis等方式，详见Monolog\handler 目录）
        $handler = new StreamHandler(storage_path($path) . date('Y-m-d').'.log');

        //设置输出格式LineFormatter(Monolog\Formatter\LineFormatter)， ignore context and extra
        $handler->setFormatter(new LineFormatter(null, null, true, true));

        $instance->pushHandler($handler);
        $instance->$method($message, $context);
    }

}
