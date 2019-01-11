<?php
//------------------------
//· 消息提醒
//---------------------
namespace denha;

class HttpTrace
{
    public static function abort($msg = 'no message', $code = '200')
    {
        header('http/1.1 ' . $code);
        header('status: ' . $code);
        if (config('debug')) {
            $e['message'] = '<p style="font-size:20px">-.-----..-.-.-.....-... : ' . $msg . '</p>';
            return include FARM_PATH . DS . 'trace' . DS . 'error.html';
        } else {
            return include FARM_PATH . DS . 'trace' . DS . '404.html';
        }
    }

}
