<?php

//------------------------
//· 模板类--类Vue模板
//-------------------------
namespace denha\view;

class Vue
{

    public $config = [
        'view'   => '',
        'left'   => '{',
        'right'  => '}',
        'suffix' => '.html',
        'root'   => VIEW_PATH,
        'prefix' =>'php-',
        'data'   => [],
    ];

    public $content;
    public $cacheTplPath; // 缓存模板地址

    public function __construct($config)
    {
        $this->config = (object)array_merge($this->config, $config);
    }

    /** 执行程序 */
    public function parseFile()
    {
        // 路径不存在
        if (!$this->config->view || $this->config->view == '') {
            $this->config->view   = $this->config->root . str_replace('.', DS, MODULE) . DS . parseName(CONTROLLER, false) . DS . ACTION . $this->config->suffix;
        }

        // 缓存模板地址 [模板文件地址+模板文件修改时间md5]
        $this->cacheTplPath = DATA_TPL_PATH . md5($this->config->view) . '.php';

        // 存在缓存文件则直接取缓存文件
        if (!is_file($this->cacheTplPath) || filemtime($this->config->view) != filemtime($this->cacheTplPath) ) {
            $this->parseFileContent();  // 获取模板内容
            $this->parseInclude(); // 解析include
            $this->parseForeach(); // 解析foreach
            $this->parseIf(); // 解析if
            $this->parseElseif(); // 解析elseif
            $this->parseElse(); // 解析else
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
     * 获取文件内容
     * @date   2019-12-17T13:43:45+0800
     * @author ChenMingjiang
     * @return [type]                   [description]
     */
    public function parseFileContent()
    {

        // 如果长度超过255, 直接当模板内容返回
        if (strlen($this->config->view) > 255) {
            $this->content = $this->config->view;
        }
        // 如果不存在路径
        elseif (!$this->config->view || $this->config->view == '') {
            $this->content = file_get_contents($this->config->view);
        }
        // 如果有模板后缀, 直接当绝对地址
        elseif (strpos($this->config->view, $this->config->suffix) > 0) {
            $this->content = file_get_contents($this->config->view);
        }
        // 如果文件存在, 直接返回文件内容
        elseif (is_file($this->config->view)) {
            $this->content = file_get_contents($this->config->view);
        } else {
            throw new Exception('视图地址[' . $this->config->view . ']不存在 ');
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
        $match = $this->match($this->content,'include');

        if($match){
            $content = $this->parseIncludeFile($match['value']);
            $this->content = str_replace($match['html'], $content, $this->content);
            $this->parseInclude();
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

    /** foreach渲染 */
    public function parseForeach(){
        $match = $this->match($this->content,'for');
        if($match){
            $content = '<?php foreach('.$match['value'].'){ ?>';
            $content .= str_replace($match['exp'], '', $match['html']);
            $content .= '<?php } ?>';

            $this->content = str_replace($match['html'], $content, $this->content);
            $this->parseForeach();
        }
    }

    public function parseIf(){
        $match = $this->match($this->content,'if');
        if($match){
            $content = '<?php if('.$match['value'].'){ ?>';
            $content .= str_replace($match['exp'], '', $match['html']);
            $content .= '<?php } ?>';

            $this->content = str_replace($match['html'], $content, $this->content);
            $this->parseIf();
        }
    }

    /**
     * 解析elseif属性
     * @return string 解析后的模板内容
     */
    public function parseElseif()
    {   
        $match = $this->match($this->content,'elseif');
        if($match){
            $content = '<?php elseif('.$match['value'].'){ ?>';
            $content .= str_replace($match['exp'], '', $match['html']);
            $content .= '<?php } ?>';

            $this->content = str_replace($match['html'], $content, $this->content);
            $this->parseElseif();
        }
    }
    /**
     * 解析else属性
     * @return string 解析后的模板内容
     */
    public function parseElse()
    {
        $match = $this->match($this->content,'else');
        if($match){
            $content = '<?php else('.$match['value'].'){ ?>';
            $content .= str_replace($match['exp'], '', $match['html']);
            $content .= '<?php } ?>';

            $this->content = str_replace($match['html'], $content, $this->content);
            $this->parseElse();
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
        $this->content = preg_replace('/' . $this->config->left . 'default:(.*?)[|](.*?)' . $this->config->right . '/is', '<?php echo !empty(\1) ? \1 : \2 ; ?>', $this->content);
        //??$xx  be !isset($xx) ?: $xx
        $this->content = preg_replace('/' . $this->config->left . '\?\?(.*?)' . $this->config->right . '/is', '<?php echo !isset(\1) ? null : \1; ?>', $this->content);
        //替换php函数 {:XXX}  be echo XXX;
        $this->content = preg_replace('/' . $this->config->left . ':(.*?)' . $this->config->right . '/', '<?php echo \1; ?>', $this->content);

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

    /**
     * 获取第一个表达式
     * @param string $content   要解析的模板内容
     * @param string $directive 指令名称
     * @param string $val       属性值
     * @return array 一个匹配的标签数组
     */
    public function match($content, $directive = '[\w]+', $val = '[^\4]*?')
    {
        $reg   = '#<(?<tag>[\w]+)[^>]*?\s(?<exp>' . preg_quote($this->config->prefix)
            . '(?<directive>' . $directive
            . ')=([\'"])(?<value>' . $val . ')\4)[^>]*>#s';
        $match = null;
        if (!preg_match($reg, $content, $match)) {
            return null;
        }

        $sub = $match[0];
        $tag = $match['tag'];
        /* 如果是单标签, 就直接返回 */
        if (substr($sub, -2) == '/>' || in_array($tag, ['br','img','meta','link','param','input'])) {
            $match['html'] = $match[0];
            return $match;
        }
        /* 查找完整标签 */
        $start_tag_len   = strlen($tag) + 1; // <div
        $end_tag_len     = strlen($tag) + 3; // </div>
        $start_tag_count = 0;
        $content_len     = strlen($content);
        $pos             = strpos($content, $sub);
        $start_pos       = $pos + strlen($sub);
        while ($start_pos < $content_len) {
            $is_start_tag = substr($content, $start_pos, $start_tag_len) == '<' . $tag;
            $is_end_tag   = substr($content, $start_pos, $end_tag_len) == "</$tag>";
            if ($is_start_tag) {
                $start_tag_count++;
            }
            if ($is_end_tag) {
                $start_tag_count--;
            }
            if ($start_tag_count < 0) {
                $match['html'] = substr($content, $pos, $start_pos - $pos + $end_tag_len);
                return $match;
            }
            $start_pos++;
        }
        return null;
    }

}
