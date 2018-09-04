<?php
//------------------------
// 图形验证码类
//-------------------------
namespace denha;

class Captcha
{
    private static $charset = 'abcdefghkmnprstuvwxyzABCDEFGHKMNPRSTUVWXYZ23456789'; //随机因子
    private static $code; //验证码
    private static $codelen = 4; //验证码长度
    private static $width   = 130; //宽度
    private static $height  = 50; //高度
    private static $img; //图形资源句柄
    private static $font; //指定的字体
    private static $fontsize = 20; //指定字体大小
    private static $fontcolor; //指定字体颜色

    //生成随机码
    private static function createCode()
    {
        $_len = strlen(self::$charset) - 1;
        for ($i = 0; $i < self::$codelen; $i++) {
            self::$code .= self::$charset[mt_rand(0, $_len)];
        }

        session('captchaCode', strtolower(self::$code));
    }

    //生成背景
    private static function createBg()
    {
        self::$img = imagecreatetruecolor(self::$width, self::$height);
        $color     = imagecolorallocate(self::$img, mt_rand(157, 255), mt_rand(157, 255), mt_rand(157, 255));
        imagefilledrectangle(self::$img, 0, self::$height, self::$width, 0, $color);
    }

    //生成文字
    private static function createFont()
    {
        $_x = self::$width / self::$codelen;
        for ($i = 0; $i < self::$codelen; $i++) {
            self::$fontcolor = imagecolorallocate(self::$img, mt_rand(0, 156), mt_rand(0, 156), mt_rand(0, 156));
            imagettftext(self::$img, self::$fontsize, mt_rand(-30, 30), $_x * $i + mt_rand(1, 5), self::$height / 1.4, self::$fontcolor, self::$font, self::$code[$i]);
        }
    }

    //生成线条、雪花
    private static function createLine()
    {
        //线条
        for ($i = 0; $i < 6; $i++) {
            $color = imagecolorallocate(self::$img, mt_rand(0, 156), mt_rand(0, 156), mt_rand(0, 156));
            imageline(self::$img, mt_rand(0, self::$width), mt_rand(0, self::$height), mt_rand(0, self::$width), mt_rand(0, self::$height), $color);
        }
        //雪花
        for ($i = 0; $i < 100; $i++) {
            $color = imagecolorallocate(self::$img, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
            imagestring(self::$img, mt_rand(1, 5), mt_rand(0, self::$width), mt_rand(0, self::$height), '*', $color);
        }
    }

    //输出
    private static function outPut()
    {
        ob_clean();
        header('Content-type:image/png');
        imagepng(self::$img);
        imagedestroy(self::$img);
        die;
    }

    //对外生成
    public static function doimg()
    {
        self::$font = dirname(__FILE__) . '/font/elephant.ttf'; //注意字体路径要写对，否则显示不了图片
        self::createBg();
        self::createCode();
        self::createLine();
        self::createFont();
        self::outPut();
    }

    // 验证
    public static function check($code)
    {
        $code = trim(strtolower($code));

        if (!$code) {
            return false;
        } elseif (!self::getCode()) {
            return false;
        } elseif ($code != self::getCode()) {
            return false;
        }

        return true;
    }

    //获取验证码
    public static function getCode()
    {
        return session('captchaCode') ? session('captchaCode') : strtolower(self::$code);
    }
}
