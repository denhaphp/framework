<?php
namespace denha;

class Smtp
{
    public $host_name;
    public $log_file; //日志文件
    public $relay_host; //SMTP服务器
    public $smtp_port; //SMTP服务器端口
    public $user; //SMTP账户
    public $pass; //SMTP密码
    public $debug = false; //是否开启调试信息
    public $time_out; //链接超时时间
    public $auth;
    public $sock;
    public $from;

    public function __construct($group = 0)
    {

        $this->init($group);

    }

    public function init($group)
    {
        $smtp = config(null, 'smtp'); //获取stmp配置信息

        $this->debug      = false;
        $this->smtp_port  = $smtp[$group]['smtp_port'];
        $this->relay_host = $smtp[$group]['smtp_host'];
        $this->time_out   = 30;
        $this->auth       = true;
        $this->user       = $smtp[$group]['smtp_user'];
        $this->pass       = $smtp[$group]['smtp_password'];
        $this->from       = $smtp[$group]['smtp_mail'] ? $smtp[$group]['smtp_mail'] : $smtp[$group]['smtp_user'];

        $this->host_name = 'localhost';
        $this->log_file  = '';
        $this->sock      = false;

    }

    public function smtp($relay_host = '', $smtp_port = 25, $auth = false, $user, $pass)
    {
        $this->debug      = false;
        $this->smtp_port  = $smtp_port;
        $this->relay_host = $relay_host;
        $this->time_out   = 30;
        $this->auth       = $auth;
        $this->user       = $user;
        $this->pass       = $pass;

        $this->host_name = 'localhost';
        $this->log_file  = '';
        $this->sock      = false;

    }

    /** debug测试 */
    public function testDebug()
    {

        $this->debug      = true;
        $this->smtp_port  = 25;
        $this->relay_host = 'smtp.163.com'; //服务器地址
        $this->time_out   = 30;
        $this->auth       = true;
        $this->user       = 'senddebug'; //账户
        $this->pass       = 'uVqe2aZ0Wc0DiAVo'; //密码
        $this->from       = 'senddebug@163.com'; //发送邮箱名

        $this->host_name = 'localhost';
        $this->log_file  = '';
        $this->sock      = false;

        $this->sendmail('cheng6251@163.com', '测试邮箱', '测邮箱信息能否通过', 'HTML');
    }

    /**
     * [sendmail description]
     * @date   2017-10-27T14:32:38+0800
     * @author ChenMingjiang
     * @param  [type]                   $to                 [接收邮箱 多个逗号分隔]
     * @param  string                   $subject            [邮件标题]
     * @param  string                   $body               [邮件内容]
     * @param  [type]                   $mailtype           [description]
     * @param  string                   $cc                 [抄送邮箱 多个逗号分隔]
     * @param  string                   $bcc                [description]
     * @param  string                   $additional_headers [邮件格式（HTML/TXT）,TXT为文本邮件]
     * @return [type]                                       [description]
     */
    public function sendmail($to, $subject = '', $body = '', $mailtype = 'HTML', $cc = '', $bcc = '', $additional_headers = '')
    {

        //获取发件邮箱名称
        $from      = $this->from;
        $mail_from = $this->get_address($this->strip_comment($from));
        $body      = preg_replace('/(^|(\r\n))(\\.)/', "$1.$3", $body);
        $header    = 'MIME-Version:1.0\r\n';
        if ($mailtype == 'HTML') {
            $header .= 'Content-Type:text/html' . PHP_EOL;
        }
        $header .= 'To: ' . $to . PHP_EOL;
        if ($cc != '') {
            $header .= "Cc: " . $cc . PHP_EOL;
        }
        $header .= 'From: $from<' . $from . '>' . PHP_EOL;
        $header .= 'Subject: ' . $subject . PHP_EOL;
        $header .= $additional_headers;
        $header .= 'Date: ' . date('r') . PHP_EOL;
        $header .= 'X-Mailer:By Redhat (PHP/' . phpversion() . ')' . PHP_EOL;
        list($msec, $sec) = explode(' ', microtime());
        $header .= 'Message-ID: <' . date('YmdHis', $sec) . '.' . ($msec * 1000000) . '.' . $mail_from . '>' . PHP_EOL;
        $TO = explode(',', $this->strip_comment($to));

        if ($cc != '') {
            $TO = array_merge($TO, explode(',', $this->strip_comment($cc)));
        }
        if ($bcc != '') {
            $TO = array_merge($TO, explode(',', $this->strip_comment($bcc)));
        }

        $sent = true;
        foreach ($TO as $rcpt_to) {
            $rcpt_to = $this->get_address($rcpt_to);
            if (!$this->smtp_sockopen($rcpt_to)) {
                $this->log_write('Error: Cannot send email to ' . $rcpt_to . PHP_EOL);
                $sent = false;
                continue;
            }
            if ($this->smtp_send($this->host_name, $mail_from, $rcpt_to, $header, $body)) {
                $this->log_write('E-mail has been sent to <' . $rcpt_to . ' > ' . PHP_EOL);
            } else {
                $this->log_write('Error: Cannot send email to <' . $rcpt_to . '>' . PHP_EOL);
                $sent = false;
            }
            fclose($this->sock);
            $this->log_write('Disconnected from remote host' . PHP_EOL);
        }
        //echo "<br>";
        //echo $header;
        return $sent;
    }

    public function smtp_send($helo, $from, $to, $header, $body = "")
    {
        if (!$this->smtp_putcmd("HELO", $helo)) {
            return $this->smtp_error("sending HELO command");
        }

        if ($this->auth) {
            if (!$this->smtp_putcmd("AUTH LOGIN", base64_encode($this->user))) {
                return $this->smtp_error("sending HELO command");
            }

            if (!$this->smtp_putcmd('', base64_encode($this->pass))) {
                return $this->smtp_error('sending HELO command');
            }
        }

        if (!$this->smtp_putcmd('MAIL', 'FROM:<' . $from . '>')) {
            return $this->smtp_error('sending MAIL FROM command');
        }

        if (!$this->smtp_putcmd('RCPT', 'TO:<' . $to . '>')) {
            return $this->smtp_error('sending RCPT TO command');
        }

        if (!$this->smtp_putcmd('DATA')) {
            return $this->smtp_error('sending DATA command');
        }

        if (!$this->smtp_message($header, $body)) {
            return $this->smtp_error('sending message');
        }

        if (!$this->smtp_eom()) {
            return $this->smtp_error('sending <CR><LF>.<CR><LF> [EOM]');
        }

        if (!$this->smtp_putcmd('QUIT')) {
            return $this->smtp_error('sending QUIT command');
        }

        return true;
    }

    public function smtp_sockopen($address)
    {
        if ($this->relay_host == '') {
            return $this->smtp_sockopen_mx($address);
        } else {
            return $this->smtp_sockopen_relay();
        }
    }

    public function smtp_sockopen_relay()
    {
        $this->log_write('Trying to ' . $this->relay_host . ':' . $this->smtp_port . PHP_EOL);
        $this->sock = @fsockopen($this->relay_host, $this->smtp_port, $errno, $errstr, $this->time_out);
        if (!($this->sock && $this->smtp_ok())) {
            $this->log_write('Error: Cannot connenct to relay host ' . $this->relay_host . PHP_EOL);
            $this->log_write('Error: ' . $errstr . ' (' . $errno . ')' . PHP_EOL);
            return false;
        }
        $this->log_write('Connected to relay host ' . $this->relay_host . PHP_EOL);
        return true;
    }

    public function smtp_sockopen_mx($address)
    {
        $domain = preg_replace('/^.+@([^@]+)$/', '$1', $address);
        if (!@getmxrr($domain, $MXHOSTS)) {
            $this->log_write("Error: Cannot resolve MX \"" . $domain . "\"" . PHP_EOL);
            return false;
        }
        foreach ($MXHOSTS as $host) {
            $this->log_write('Trying to ' . $host . ':' . $this->smtp_port . PHP_EOL);
            $this->sock = @fsockopen($host, $this->smtp_port, $errno, $errstr, $this->time_out);
            if (!($this->sock && $this->smtp_ok())) {
                $this->log_write('Warning: Cannot connect to mx host ' . $host . PHP_EOL);
                $this->log_write("Error: " . $errstr . " (" . $errno . ")" . PHP_EOL);
                continue;
            }
            $this->log_write('Connected to mx host ' . $host . PHP_EOL);
            return true;
        }
        $this->log_write('Error: Cannot connect to any mx hosts (' . implode(', ', $MXHOSTS) . ')' . PHP_EOL);
        return false;
    }

    public function smtp_message($header, $body)
    {
        fputs($this->sock, $header . PHP_EOL . $body);
        $this->smtp_debug("> " . str_replace(PHP_EOL, "\n" . "> ", $header . "\n> " . $body . "\n> "));

        return true;
    }

    public function smtp_eom()
    {
        fputs($this->sock, "\r\n.\r\n");
        $this->smtp_debug(". [EOM]\n");

        return $this->smtp_ok();
    }

    public function smtp_ok()
    {
        $response = str_replace(PHP_EOL, "", fgets($this->sock, 512));
        $this->smtp_debug($response . PHP_EOL);

        if (!preg_match("/^[23]/", $response)) {
            fputs($this->sock, "QUIT\r\n");
            fgets($this->sock, 512);
            $this->log_write("Error: Remote host returned " . $response . PHP_EOL);
            return false;
        }
        return true;
    }

    public function smtp_putcmd($cmd, $arg = "")
    {
        if ($arg != "") {
            if ($cmd == "") {
                $cmd = $arg;
            } else {
                $cmd = $cmd . " " . $arg;
            }

        }

        fputs($this->sock, $cmd . PHP_EOL);
        $this->smtp_debug("> " . $cmd . PHP_EOL);

        return $this->smtp_ok();
    }

    public function smtp_error($string)
    {
        $this->log_write('Error: Error occurred while ' . $string . '.' . PHP_EOL);
        return false;
    }

    public function log_write($message)
    {
        $this->smtp_debug($message);

        if ($this->log_file == '') {
            return true;
        }

        $message = date('M d H:i:s ') . get_current_user() . '[' . getmypid() . ']: ' . $message;
        if (!@file_exists($this->log_file) || !($fp = @fopen($this->log_file, 'a'))) {
            $this->smtp_debug("Warning: Cannot open log file \"" . $this->log_file . "\"" . PHP_EOL);
            return false;
        }
        flock($fp, LOCK_EX);
        fputs($fp, $message);
        fclose($fp);

        return true;
    }

    public function strip_comment($address)
    {
        $comment = "/\\([^()]*\\)/";
        while (preg_match($comment, $address)) {
            $address = ereg_replace($comment, "", $address);
        }

        return $address;
    }

    public function get_address($address)
    {
        $address = preg_replace("/([ \t\r\n])+/", "", $address);
        $address = preg_replace("/^.*<(.+)>.*$/", "$1", $address);

        return $address;
    }

    public function smtp_debug($message)
    {
        if ($this->debug) {
            echo $message . "<br>";
        }
    }

    public function get_attach_type($image_tag)
    {
        //

        $filedata = array();

        $img_file_con = fopen($image_tag, "r");
        unset($image_data);
        while ($tem_buffer = AddSlashes(fread($img_file_con, filesize($image_tag)))) {
            $image_data .= $tem_buffer;
        }

        fclose($img_file_con);

        $filedata['context']  = $image_data;
        $filedata['filename'] = basename($image_tag);
        $extension            = substr($image_tag, strrpos($image_tag, "."), strlen($image_tag) - strrpos($image_tag, "."));
        switch ($extension) {
            case '.gif':
                $filedata['type'] = 'image/gif';
                break;
            case '.gz':
                $filedata['type'] = 'application/x-gzip';
                break;
            case '.htm':
                $filedata['type'] = 'text/html';
                break;
            case '.html':
                $filedata['type'] = 'text/html';
                break;
            case '.jpg':
                $filedata['type'] = 'image/jpeg';
                break;
            case '.tar':
                $filedata['type'] = 'application/x-tar';
                break;
            case '.txt':
                $filedata['type'] = 'text/plain';
                break;
            case '.zip':
                $filedata['type'] = 'application/zip';
                break;
            default:
                $filedata['type'] = 'application/octet-stream';
                break;
        }

        return $filedata;
    }
}
