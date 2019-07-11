<?php
//------------------------
//· 模板类
//-------------------------
namespace denha;

class Template
{
    public $left  = '{';
    public $right = '}';
    public $viewPath;
    public $loadPath;
    public $content;
    public $editTime; // 最新修改时间

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
        //{default:$xxx|"xxx"}
        $this->content = preg_replace('/' . $this->left . 'default:(.*?)[|](.*?)' . $this->right . '/is', '<?php echo !empty(\1) ? \1 : \2 ; ?>', $this->content);
        //??$xx  be !isset($xx) ?: $xx
        $this->content = preg_replace('/' . $this->left . '\?\?(.*?)' . $this->right . '/is', '<?php echo !isset(\1) ? null : \1; ?>', $this->content);
        //机器翻译标签 {FY:xxx:en}
        $this->content = preg_replace('/' . $this->left . 'FY:\$(.*?):(.*?)' . $this->right . '/is', '<?php echo \'{FY:\'.$\1.\':\2}\'; ?>', $this->content);
        //替换php函数 {F:XXX}  be echo XXX;
        $this->content = preg_replace('/' . $this->left . 'F:(.*?)' . $this->right . '/', '<?php echo \1; ?>', $this->content); //替换php函数 {:XXX}  be echo XXX;
        $this->content = preg_replace('/' . $this->left . ':(.*?)' . $this->right . '/', '<?php echo \1; ?>', $this->content);
        //替换{if XXX}
        $this->content = preg_replace('/' . $this->left . 'if(.*?)' . $this->right . '/is', '<?php if(\1){; ?>', $this->content);
        //替换{else}
        $this->content = preg_replace('/' . $this->left . 'else' . $this->right . '/is', '<?php }else{ ?>', $this->content);
        //替换{elseif XXX}
        $this->content = preg_replace('/' . $this->left . 'elseif(.*?)' . $this->right . '/is', '<?php }elseif(\1){ ?>', $this->content);
        //替换{/if}
        $this->content = preg_replace('/' . $this->left . '\/if' . $this->right . '/is', '<?php } ?>', $this->content);

        // 替换配置字符串
        $replaceStr = config('view_replace_str');
        foreach ($replaceStr as $key => $value) {
            if (strpos($this->content, $key) !== false) {
                $this->content = str_replace($key, $value, $this->content);
            }
        }

        $this->saveFile();

    }

    // 获取最新文件修改时间
    public function editTimeDiff($time)
    {
        if (!$this->editTime) {
            $this->editTime = $time;
        }

        if ($this->editTime < $time) {
            $this->editTime = $time;
        }

        return $this->editTime;
    }

    public function saveFile()
    {
        //如果没有写入权限尝试修改权限 如果修改后还是失败 则跳过
        if (!isWritable(DATA_PATH)) {
            return false;
        }

        is_dir(DATA_TPL_PATH) ? '' : mkdir(DATA_TPL_PATH, 0755, true);

        $cacheMd5       = md5($this->viewPath);
        $this->loadPath = DATA_TPL_PATH . $cacheMd5 . '.php';
        $file           = fopen($this->loadPath, 'w');

        fwrite($file, $this->content);
        fclose($file);

        // $filemtime = $this->editTimeDiff(filemtime($this->viewPath)); // 获取最新同步修改时间
        $filemtime = filemtime($this->viewPath);

        touch($this->loadPath, $filemtime, $filemtime); // 更新缓存模板时间

    }

    public function stampInclude()
    {
        $regular = '#' . $this->left . 'include\s(.*?)' . $this->right . '#is';
        preg_match_all($regular, $this->content, $matches);
        if ($matches) {
            foreach ($matches[1] as $key => $value) {
                $value = trim(str_replace('/', DS, $value));
                if (!$value) {
                    $path = VIEW_PATH . DS . MODULE . DS . parseName(CONTROLLER, false) . DS . ACTION . '.html';
                }
                //绝对路径 appliaction目录开始
                elseif (stripos($value, DS) === 0) {
                    $path = VIEW_PATH . $value . '.html';
                }
                //相对路径
                else {
                    $path = VIEW_PATH . DS . MODULE . DS . $value . '.html';
                }

                $path = str_replace('\\', DS, $path);

                if (is_file($path)) {
                    $file      = fopen($path, 'r');
                    $content   = fread($file, filesize($path));
                    $filemtime = filemtime($path);

                    // 保存模板Include标签文件更新时间
                    // $cacheTime = cache('template_include_cache_time');
                    // $cacheTime = is_array($cacheTime) ? $cacheTime : [];

                    // $cacheTime['view'][md5($this->viewPath)][md5($path)] = $filemtime;
                    // $cacheTime['include'][md5($path)]                    = $path;
                    // cache('template_include_cache_time', $cacheTime);

                    // // 最新修改时间
                    // $this->editTimeDiff(filemtime($path));

                    //替换模板变量
                    $this->content = str_replace($matches[0][$key], $content, $this->content);
                    // 循环模板
                    $this->stampInclude();
                } else {

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
                    $this->content = str_replace($matches[0][$key], '<?php if(!empty(' . $matches[1][$key] . ')){ foreach(' . $matches[1][$key] . ' as ' . trim($rowTmp[0]) . ' => ' . trim($rowTmp[1]) . '){ ?>', $this->content);
                } else {
                    $this->content = str_replace($matches[0][$key], '<?php if(!empty(' . $matches[1][$key] . ')){ foreach(' . $matches[1][$key] . ' as ' . $matches[2][$key] . '){ ?>', $this->content);
                }
            }

            $this->content = str_replace($this->left . '/loop' . $this->right, '<?php }} ?>', $this->content);
        }
    }
}
