<?php
namespace denha;

class Pages
{
    public $total;
    public $pageNo;
    public $pageSize;
    public $pageUrl;
    public $pageList;
    public $allPage;
    public $showPageFigure;

    public function __construct($total = 0, $pageNo = '1', $pageSize = '20', $pageUrl = '', $showPageFigure = 4)
    {
        $this->total          = $total ? $total : $this->total;
        $this->pageNo         = $pageNo ? max($pageNo, 1) : max($this->pageNo, 1);
        $this->pageSize       = $pageSize ? $pageSize : $this->pageSize;
        $this->pageUrl        = $pageUrl ? $pageUrl : $this->pageUrl;
        $this->showPageFigure = $showPageFigure ? $showPageFigure : $this->showPageFigure;
    }

    protected function pages()
    {
        if ($this->total == 0) {
            return false;
        }
        $this->allPage = (int) ceil($this->total / $this->pageSize);
        if ($this->pageNo > $this->allPage) {
            abort('请选择正确的页码');
        }

        $startPage = 1;
        $endPage   = $this->allPage;
        if ($this->allPage > $this->showPageFigure && $this->pageNo + $this->showPageFigure <= $this->allPage) {
            if ($this->pageNo == 0) {
                $startPage = 1;
                $endPage   = $this->pageNo + $this->showPageFigure;
            } else {
                $startPage = $this->pageNo;
                $endPage   = $this->pageNo - 1 + $this->showPageFigure;
            }
        } else {
            if ($this->allPage - $this->showPageFigure <= 0) {
                $startPage = 1;
                $endPage   = $this->allPage;
            } else {
                $startPage = $this->allPage - $this->showPageFigure + 1;
                $endPage   = $this->allPage;
            }
        }

        for ($i = $startPage; $i <= $endPage; $i++) {
            $listTmp[] = $i;
        }

        $this->pageList = $listTmp;

        $data['pageNo']   = $this->pageNo;
        $data['pageSize'] = $this->pageSize;
        $data['allPage']  = $this->allPage;
        $data['pageList'] = $listTmp;
        $data['limit']    = max(($this->pageNo + 1), 0) * $this->pageSize;
        $data['pageUrl']  = $this->pageUrl;

        return $data;

    }

    public function loadConsole()
    {
        $this->pages();
        if ($this->allPage > 1) {
            if (stripos($this->pageUrl, '?') === false) {
                $this->pageUrl = $this->pageUrl . '?pageNo=';
            } else {
                $this->pageUrl = $this->pageUrl . '&pageNo=';
            }

            $pages = '<div class="pull-right page-box">' . "\r\n";
            $pages .= '<div class="pagination-info">当前' . $this->pageNo . ' - ' . ($this->pageNo * $this->pageSize) . '/' . ($this->allPage * $this->pageSize) . ' 条</div>' . "\r\n";
            $pages .= '<ul class="pagination" >' . "\r\n";
            if ($this->pageNo > $this->showPageFigure) {
                $pages .= '<li><a href="' . $this->pageUrl . '1">«</a></li>' . "\r\n";
            }

            if ($this->pageNo > 1) {
                $pages .= '<li><a href="' . $this->pageUrl . max(($this->pageNo - 1), 1) . '">‹</a></li>' . "\r\n";
            }

            if ($this->pageList) {
                foreach ($this->pageList as $key => $value) {
                    $value == $this->pageNo ? $class = 'class="active"' : $class = '';
                    $pages .= '<li ' . $class . '><a href="' . $this->pageUrl . $value . '">' . $value . '</a></li>' . "\r\n";
                }
            }
            $pages .= '<li><a href="' . $this->pageUrl . min(($this->pageNo + 1), $this->allPage) . '">›</a></li>' . "\r\n";
            $pages .= '<li><a href="' . $this->pageUrl . $this->allPage . '">»</a></li>' . "\r\n";
            $pages .= '</ul>' . "\r\n";
            $pages .= '<span class="pagination-goto">' . "\r\n";
            $pages .= '<input type="text" class="form-control w40">' . "\r\n";
            $pages .= '<button class="btn btn-default" type="button" >GO</button>' . "\r\n";
            $pages .= '</span>' . "\r\n";
            $pages .= '</div>' . "\r\n";

            return $pages;
        }
    }

    public function loadPc()
    {
        $this->pages();
        if ($this->allPage > 1) {
            if (stripos($this->pageUrl, '?') === false) {
                $this->pageUrl = $this->pageUrl . '?pageNo=';
            } else {
                $this->pageUrl = $this->pageUrl . '&pageNo=';
            }

            $pages = '<div class="pull-right page-box" pages-data="pages" param-data="param" page-function="getPageList()">' . "\r\n";
            $pages .= '<div class="pagination-info">当前' . $this->pageNo . ' - ' . ($this->pageNo * $this->pageSize) . '/' . ($this->allPage * $this->pageSize) . ' 条</div>' . "\r\n";
            $pages .= '<ul class="pagination hidden-sm hidden-xs" >' . "\r\n";
            if ($this->pageNo > $this->showPageFigure) {
                $pages .= '<li><a href="' . $this->pageUrl . '1">«</a></li>' . "\r\n";
            }

            if ($this->pageNo > 1) {
                $pages .= '<li><a href="' . $this->pageUrl . max(($this->pageNo - 1), 1) . '">‹</a></li>' . "\r\n";
            }

            if ($this->pageList) {
                foreach ($this->pageList as $key => $value) {
                    $value == $this->pageNo ? $class = 'class="active"' : $class = '';
                    $pages .= '<li ' . $class . '><a href="' . $this->pageUrl . $value . '">' . $value . '</a></li>' . "\r\n";
                }
            }
            $pages .= '<li><a href="' . $this->pageUrl . min(($this->pageNo + 1), $this->allPage) . '">›</a></li>' . "\r\n";
            $pages .= '<li><a href="' . $this->pageUrl . $this->allPage . '">»</a></li>' . "\r\n";
            $pages .= '</ul>' . "\r\n";
            $pages .= '<span class="pagination-goto">' . "\r\n";
            $pages .= '</span>' . "\r\n";
            $pages .= '</div>' . "\r\n";

            return $pages;
        }
    }
}
