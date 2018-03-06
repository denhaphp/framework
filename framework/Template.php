<?php
namespace denha;

class Template
{
    public $left  = '{';
    public $right = '}';
    public $viewPath;
    public $loadPath;
    public $content;

    public function __construct($viewPath)
    {
        $this->viewPath = $viewPath;
    }

    public function getContent()
    {
        $file          = fopen($this->viewPath, 'r');
        $this->content = fread($file, filesize($this->viewPath));
        $this->stampInclude();

        $this->stampForeach();

        //{$xxx} be echo $xxx;
        $this->content = preg_replace('/' . $this->left . '\$(.*?)' . $this->right . '/is', '<?php echo $\1; ?>', $this->content);
        //??$xx  be !isset($xx) ?: $xx
        $this->content = preg_replace('/' . $this->left . '\?\?(.*?)' . $this->right . '/is', '<?php echo !isset(\1) ? null : \1; ?>', $this->content);
        //机器翻译标签 {FY:xxx:en}
        $this->content = preg_replace('/' . $this->left . 'FY:\$(.*?):(.*?)' . $this->right . '/is', '<?php echo \'{FY:\'.$\1.\':\2}\'; ?>', $this->content);
        //替换php函数 {F:XXX}  be echo XXX;
        $this->content = preg_replace('/' . $this->left . 'F:(.*?)' . $this->right . '/', '<?php echo \1; ?>', $this->content);
        //替换{if XXX}
        $this->content = preg_replace('/' . $this->left . 'if(.*?)' . $this->right . '/is', '<?php if(\1){; ?>', $this->content);
        //替换{else}
        $this->content = preg_replace('/' . $this->left . 'else' . $this->right . '/is', '<?php }else{ ?>', $this->content);
        //替换{elseif XXX}
        $this->content = preg_replace('/' . $this->left . 'elseif(.*?)' . $this->right . '/is', '<?php }elseif(\1){ ?>', $this->content);
        //替换{/if}
        $this->content = preg_replace('/' . $this->left . '\/if' . $this->right . '/is', '<?php } ?>', $this->content);
        $this->saveFile();

    }

    public function saveFile()
    {
        //如果没有写入权限尝试修改权限 如果修改后还是失败 则跳过
        if (!isWritable(DATA_PATH)) {
            return false;
        }

        $cacheMd5       = md5($this->viewPath);
        $this->loadPath = DATA_PATH . $cacheMd5 . '.php';
        $file           = fopen($this->loadPath, 'w');
        fwrite($file, $this->content);
        fclose($file);
        //同步修改时间
        touch($this->loadPath, filemtime($this->viewPath), filemtime($this->viewPath));

    }

    public function stampInclude()
    {
        $regular = '#' . $this->left . 'include\s(.*?)' . $this->right . '#is';
        preg_match_all($regular, $this->content, $matches);
        if ($matches) {
            foreach ($matches[1] as $key => $value) {
                $value = trim(str_replace('/', DS, $value));
                if (!$value) {
                    $path = APP_PATH . APP . DS . 'view' . DS . MODULE . DS . CONTROLLER . DS . ACTION . '.html';
                }
                //绝对路径 appliaction目录开始
                elseif (stripos($value, DS) === 0) {
                    $path = APP_PATH . APP . DS . 'view' . $value . '.html';
                }
                //相对路径
                else {
                    $path = APP_PATH . APP . DS . 'view' . DS . MODULE . DS . $value . '.html';
                }

                $path = str_replace('\\', DS, $path);

                if (is_file($path)) {
                    $file    = fopen($path, 'r');
                    $content = fread($file, filesize($path));
                    //替换模板变量
                    $this->content = str_replace($matches[0][$key], $content, $this->content);
                }
            }
        }

    }

    public function stampForeach()
    {
        $regular = '#' . $this->left . 'loop\s(.*?)\s(.*?)' . $this->right . '#is';
        preg_match_all($regular, $this->content, $matches);
        if ($matches) {
            foreach ($matches[0] as $key => $value) {
                if (stripos($matches[2][$key], ' ') !== false) {
                    $rowTmp        = explode(' ', $matches[2][$key]);
                    $this->content = str_replace($matches[0][$key], '<?php if(' . $matches[1][$key] . '){ foreach(' . $matches[1][$key] . ' as ' . trim($rowTmp[0]) . ' => ' . trim($rowTmp[1]) . '){ ?>', $this->content);
                } else {
                    $this->content = str_replace($matches[0][$key], '<?php if(' . $matches[1][$key] . '){ foreach(' . $matches[1][$key] . ' as ' . $matches[2][$key] . '){ ?>', $this->content);
                }
            }

            $this->content = str_replace($this->left . '/loop' . $this->right, '<?php }} ?>', $this->content);
        }
    }
}
