<?php
namespace denha;

class Log
{
    public static function error($msg = 'no message')
    {
        if (config('trace')) {
            $e['message'] = '<p style="font-size:20px">-.-----..-.-.-.....-... : ' . $msg . '</p>';
            return include FARM_PATH . DS . 'trace' . DS . 'error.html';
        } else {
            header("http/1.1 404 not found");
            header("status: 404 not found");
            return include FARM_PATH . DS . 'trace' . DS . '404.html';
        }
    }

}
