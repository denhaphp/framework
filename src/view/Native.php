<?php
//------------------------
//· 模板类--原生模板
//-------------------------
namespace denha\view;

class Native
{
    public $config = [
        'view'   => '',
        'left'   => '{',
        'right'  => '}',
        'suffix' => '.html',
        'root'   => VIEW_PATH,
        'data'   => [],
    ];
    
    public $content;
    public $cacheTplPath; // 缓存模板地址

    public function __construct($config)
    {
        $this->config = (object) array_merge($this->config, $config);
    }

    /** 执行程序 */
    public function parseFile()
    {   

        // 如果长度超过255, 直接当模板内容返回
        if (strlen($this->config->view) > 255) {
            return $this->config->view;
        }

        $this->parseViewPath();  // 解析文件地址
            
        // 缓存模板地址 [模板文件地址+模板文件修改时间md5]
        $this->cacheTplPath = DATA_TPL_PATH . md5($this->config->view) . '.php';    

        // 存在缓存文件则直接取缓存文件
        if (!is_file($this->cacheTplPath)  || filemtime($this->config->view) != filemtime($this->cacheTplPath) ) {
            $this->getContent();  // 获取模板内容
            $this->parseInclude(); // 解析include
            $this->parseForeach(); // 解析foreach
            $this->parseValue(); // 解析foreach
            $this->parseConfStr(); // 解析foreach
            $this->save(); // 保存模板缓存
        } 

        extract($this->config->data, EXTR_OVERWRITE);
        
        ob_start();
        ob_implicit_flush(0);
        include $this->cacheTplPath;

        $content = ob_get_clean();

        return $content;
    }

    /**
     * 解析文件地址
     * @date   2019-12-20T16:19:06+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function parseViewPath()
    {

        // 不存在模板路径
        if (!$this->config->view || $this->config->view == '') {
            $this->config->view   = $this->config->root . str_replace('.', DS, MODULE) . DS . parseName(CONTROLLER, false) . DS . ACTION . $this->config->suffix;
        }
        // 如果有模板后缀, 直接当绝对地址
        elseif (strpos($this->config->view, $this->config->suffix) > 0) {
            $this->config->view = $this->config->root.$this->config->view;
        }
        // 相对路径
        elseif(stripos($this->config->view, '/') !== 0){
            $this->config->view = $this->config->root.str_replace('.', DS, MODULE) . DS .$this->config->view.$this->config->suffix;
        }

    }

    /**
     * 获取文件内容
     * @date   2019-12-17T13:43:45+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function getContent(){

        if (is_file($this->config->view)) {
            $this->content = file_get_contents($this->config->view);
        } else {
            throw new \Exception('视图地址[' . $this->config->view . ']不存在 ');
        }
    }

    /**
     * 解析include
     * @date   2019-12-17T13:46:53+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function parseInclude()
    {   
        $regular = '#' . $this->config->left . 'include\s(.*?)' . $this->config->right . '#is';
        preg_match_all($regular, $this->content, $matches);
        if ($matches) {
            foreach ($matches[1] as $key => $tpl) {
                $content = $this->parseIncludeFile($tpl);
                //替换模板变量
                $this->content = str_replace($matches[0][$key], $content, $this->content);
                // 循环模板
                $this->parseInclude();
            }
        }

    }

    /**
     * 解析include文件路径
     * @date   2019-12-17T13:46:58+0800
     * @author ChenMingjiang
     * @param  [type]                   $tpl [description]
     * @return [type]                   [description]
     */
    public function parseIncludeFile($tpl)
    {
        $tpl = trim(str_replace('/', DS, $tpl));
        if (!$tpl) {
            $path = $this->config->root . DS . MODULE . DS . parseName(CONTROLLER, false) . DS . ACTION . $this->config->suffix;
        }
        // 绝对路径 appliaction目录开始
        elseif (stripos($tpl, DS) === 0) {
            $path = $this->config->root . $tpl . $this->config->suffix;
        }
        // 相对路径
        else {
            $path = $this->config->root . DS . MODULE . DS . $tpl . $this->config->suffix;
        }

        $path = str_replace('\\', DS, $path);

        if (is_file($path)) {
            $content = file_get_contents($path);
        } else {
            $content = '';
        }

        return $content;
    }
    

    /**
     * 解析foreach
     * @date   2019-12-17T13:47:02+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function parseForeach()
    {
        $regular = '#' . $this->config->left . 'loop\s(.*?)\s(.*?)' . $this->config->right . '#is';
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

            $this->content = str_replace($this->config->left . '/loop' . $this->config->right, '<?php }} ?>', $this->content);
        }
    }

    /**
     * 解析值
     * @date   2019-12-17T13:46:34+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function parseValue()
    {
        // {$vo.name} be {$vo["name"]}
        $this->content = preg_replace('/' . $this->config->left . '(\$[\w\[\"\]]*)\.(\w*)([^\{\}]*)' . $this->config->right . '/', '{\1["\2"]\3}', $this->content);
        $this->content = preg_replace('/' . $this->config->left . '(\$[\w\[\"\]]*)\.(\w*)([^\{\}]*)' . $this->config->right . '/', '{\1["\2"]\3}', $this->content);
        // {$xxx} be echo $xxx;
        $this->content = preg_replace('/' . $this->config->left . '\$(.*?)' . $this->config->right . '/is', '<?php echo $\1; ?>', $this->content);
        // {default:$xxx|"xxx"}
        $this->content = preg_replace('/' . $this->config->left . 'default:(.*?)[|](.*?)' . $this->config->right . '/is', '<?php echo \1 ?: \2 ; ?>', $this->content);
        //??$xx  be !isset($xx) ?: $xx
        $this->content = preg_replace('/' . $this->config->left . '\?\?(.*?)' . $this->config->right . '/is', '<?php echo \1 ?? \1; ?>', $this->content);
        // 机器翻译标签 {FY:xxx:en}
        $this->content = preg_replace('/' . $this->config->left . 'FY:\$(.*?):(.*?)' . $this->config->right . '/is', '<?php echo \'{FY:\'.$\1.\':\2}\'; ?>', $this->content);
        // 替换php函数 {F:XXX}  be echo XXX;
        $this->content = preg_replace('/' . $this->config->left . 'F:(.*?)' . $this->config->right . '/', '<?php echo \1; ?>', $this->content); //替换php函数 {:XXX}  be echo XXX;
        $this->content = preg_replace('/' . $this->config->left . ':(.*?)' . $this->config->right . '/', '<?php echo \1; ?>', $this->content);
        // 替换{if XXX}
        $this->content = preg_replace('/' . $this->config->left . 'if(.*?)' . $this->config->right . '/is', '<?php if(\1){; ?>', $this->content);
        // 替换{else}
        $this->content = preg_replace('/' . $this->config->left . 'else' . $this->config->right . '/is', '<?php }else{ ?>', $this->content);
        // 替换{elseif XXX}
        $this->content = preg_replace('/' . $this->config->left . 'elseif(.*?)' . $this->config->right . '/is', '<?php }elseif(\1){ ?>', $this->content);
        // 替换{/if}
        $this->content = preg_replace('/' . $this->config->left . '\/if' . $this->config->right . '/is', '<?php } ?>', $this->content);

        return $this->content;
    }

    /**
     * 替换配置字符串
     * @date   2019-12-17T13:46:48+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function parseConfStr()
    {
        $replaceStr = config('view_replace_str');
        foreach ($replaceStr as $key => $value) {
            if (strpos($this->content, $key) !== false) {
                $this->content = str_replace($key, $value, $this->content);
            }
        }
    }

     /**
     * 保存编译文件
     * @date   2019-12-17T09:37:19+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public  function save()
    {
        // 创建缓存目录
        is_dir(DATA_TPL_PATH) ? '' : mkdir(DATA_TPL_PATH, 0755, true);

        file_put_contents($this->cacheTplPath,$this->content);

        $time = filemtime($this->config->view);

        touch($this->cacheTplPath, $time, $time); // 更新缓存模板时间

    }
}
