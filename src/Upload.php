<?php
//------------------------
//· 上传类
//-------------------------
namespace denha;

/**
 * 我的文件上传类
 *
 * 未完成的功能：
 * 1.对目标目录是否存在的判断
 * 2.如果上传时出现重名，自动重命名
 *
 * @author M.Q
 */
class Upload
{
    /**
     * PHP上传类upload.php上传文件的信息，此值由构造函数取得，如果上传文件失败或出错或未上传，则此值为false
     *
     * @var array
     */
    public $file = false;
    public $path; //保存路径
    public $type; //保存类型
    public $size; //附件大小
    public $error = false; //错误提示;
    /**
     * 构造函数：取得上传文件的信息
     *
     * 如果在上传文件的工程中发生错误，那么出错的文件不会放在结果中返回，结果中的文件都是可用的
     *
     * @param string $tag form表单中<input>标签中name属性的值，例<input name="p" type="file">
     *
     * 例1，上传单个文件：
     * <input name="upfile" type="file">
     *
     * 例2，上传多个文件：
     * <input name="upfile[]" type="file">
     * <input name="upfile[]" type="file">
     *
     */
    public function __construct($tag = '')
    {

        $file = isset($_FILES[$tag]) ? $_FILES[$tag] : '';

        //判断上传文件
        if (!isset($file) || empty($file)) {
            return array('status' => false, 'msg' => '请先上传文件');
        }

        $num = count($file['name']); //PHP上传类upload.php上传文件的个数

        $data = array(); //用来保存上传文件的信息的数组
        //上传了多个文件
        if ($num > 1) {
            for ($i = 0; $i < $num; $i++) {
                $d             = array();
                $d['name']     = $file['name'][$i];
                $d['type']     = $file['type'][$i];
                $d['tmp_name'] = $file['tmp_name'][$i];
                $d['error']    = $file['error'][$i];
                $d['size']     = $file['size'][$i];
                $pics          = explode('.', $file['name'][$i]);
                $d['postfix']  = $pics[count($pics) - 1];
                $d['filename'] = date('Ymdh', time()) . rand(10000, 99999) . '.' . $d['postfix'];

                if ($d['error'] == 0) {
                    $data[] = $d;
                } else {
                    @unlink($d['tmp_name']);
                }
            }
        }
        //只上传了一个文件
        else {
            $d             = array();
            $d['name']     = $file['name'];
            $d['type']     = $file['type'];
            $d['tmp_name'] = $file['tmp_name'];
            $d['error']    = $file['error'];
            $pics          = explode('.', $file['name']);
            $d['postfix']  = $pics[count($pics) - 1];
            $d['filename'] = date('Ymdh', time()) . rand(10000, 99999) . '.' . $d['postfix'];

            if ($d['error'] == 0) {
                $data[] = $d;
            } else {
                @unlink($d['tmp_name']);
            }
        }

        if (empty($data)) {
            return;
        }

        $this->file = $data; //保存上传文件的信息
    }

    /*获取file*/
    public function getFile($tag)
    {
        $file = $_FILES[$tag];

        if (!isset($file) || empty($file)) {
            return; //没有上传文件
        }

        $num = count($file['name']); //PHP上传类upload.php上传文件的个数

        $data = array(); //用来保存上传文件的信息的数组
        //上传了多个文件
        if ($num > 1) {
            for ($i = 0; $i < $num; $i++) {
                $d             = array();
                $d['name']     = $file['name'][$i];
                $d['type']     = $file['type'][$i];
                $d['tmp_name'] = $file['tmp_name'][$i];
                $d['error']    = $file['error'][$i];
                $d['size']     = $file['size'][$i];
                $pics          = explode('.', $file['name'][$i]);
                $d['postfix']  = $pics[count($pics) - 1];
                $d['filename'] = date('Ymdh', time()) . rand(10000, 99999) . '.' . $d['postfix'];

                if ($d['error'] == 0) {
                    $data[] = $d;
                } else {
                    @unlink($d['tmp_name']);
                }
            }
        }
        //只上传了一个文件
        else {
            $d             = array();
            $d['name']     = $file['name'];
            $d['type']     = $file['type'];
            $d['tmp_name'] = $file['tmp_name'];
            $d['error']    = $file['error'];
            $pics          = explode('.', $file['name']);
            $d['postfix']  = $pics[count($pics) - 1];
            $d['filename'] = date('Ymdh', time()) . rand(10000, 99999) . '.' . $d['postfix'];

            if ($d['error'] == 0) {
                $data[] = $d;
            } else {
                @unlink($d['tmp_name']);
            }
        }

        if (empty($data)) {
            return;
        }

        $this->file = $data; //保存上传文件的信息
    }

    /*执行*/
    public function upload()
    {

        if ($this->path == '') {
            $this->error = '请设置保存路径';
            return;
        }

        if (is_array($this->file)) {
            foreach ($this->file as $file) {
                $rest = $this->save($file, $this->path);
                for ($i = 0, $n = count($this->file); $i < $n; $i++) {
                    if ($rest) {
                        $this->file[$i]['url'] = 'http://' . $_SERVER['HTTP_HOST'] . $this->path . '/' . $this->file[$i]['filename'];
                    } else { $this->file[$i]['url'] = '';}
                }
            }
            return $rest;
        }
    }

    /*保存路径*/
    public function path($path)
    {
        $this->path = "/" . $path;
        if (!is_dir($_SERVER['DOCUMENT_ROOT'] . $this->path)) {
            mkdir($_SERVER['DOCUMENT_ROOT'] . $this->path);
        }
    }

    /*上次类型*/
    public function type($val)
    {
        $this->type = $val;
    }
    /**
     * 将上传的文件从临时文件夹移动到目标路径
     *
     * @param array $src 文件信息数组，是$file数组的其中一个元素（仍然是数组）
     * @param string $destpath 上传的目标路径
     * @param string $filename 上传后的文件名，如果为空，则使用上传时的文件名
     * @return bool
     */
    public function save($src, $destpath = '', $filename = null)
    {
        if ($src['error'] != 0) {return false;}

        if ($this->path != '') {$destpath = $_SERVER['DOCUMENT_ROOT'] . $this->path;}

        $srcTName = $src['tmp_name']; //原始上传文件的临时文件名
        $srcFName = $src['name']; //原始文件名

        //如果$filename参数为空，则使用上传时的文件名
        if (empty($filename)) {
            $filename = $src['filename'];

        }

        //$dest是文件最终要复制到的路径和文件名
        if (empty($destpath)) {
            $dest = $filename;
        } else {
            //修正路径中的斜杠，将末尾的\修改为/，如果末尾不是\也不是/，则给末尾添加一个/
            $pathend = $destpath[strlen($destpath) - 1]; //上传的目标路径的最后一个字符
            if ($pathend == '\\') {
                $dest = substr_replace($destpath, '/', strlen($destpath) - 1) . $filename;
            } else if ($pathend != '/') {
                $dest = $destpath . '/' . $filename;
            } else {
                $dest = $destpath . $filename;
            }
        }

        //上传文件成功
        if (@move_uploaded_file($srcTName, $dest)) {

            return true;
        } else {
            $this->error = '上传文件失败' . ":" . $filename;
            return false;
        }
    }

    //取得上传文件的信息
    public function getFileInfo()
    {
        return $this->file;
    }

    public function getError()
    {
        return $this->error;
    }
}
