<?php
//------------------------
//· 分页模块
//---------------------

declare (strict_types = 1);

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

        $this->init();
    }

    public function getLimit()
    {
        return [max(($this->pageNo - 1), 0) * $this->pageSize, $this->pageSize];
    }

    protected function init()
    {
        if ($this->total == 0) {
            return false;
        }

        $this->allPage = (int) ceil($this->total / $this->pageSize);

        if ($this->pageNo > $this->allPage) {
            $this->pageNo = $this->allPage;
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

    public function pageJump()
    {
        $pages = [10, 20, 50, 100];

        $html = '<select class="form-control" onchange="self.location.href=options[selectedIndex].value" style="display:inline-block;width:auto">' . PHP_EOL;
        foreach ($pages as $value) {
            $isChecked = $this->pageSize == $value ? 'selected="selected"' : '';
            $html .= '<option value="' . $this->pageUrl . $this->pageNo . '&pageSize=' . $value . '" ' . $isChecked . '>' . $value . '</option>' . PHP_EOL;
        }

        $html .= '</select>' . PHP_EOL;

        return $html;
    }

    public function loadConsole()
    {

        if ($this->allPage > 1) {
            if (stripos($this->pageUrl, '?') === false) {
                $this->pageUrl = $this->pageUrl . '?pageNo=';
            } else {
                $this->pageUrl = $this->pageUrl . '&pageNo=';
            }

            $pages = '<div class="pull-right page-box">' . PHP_EOL;
            $pages .= '<div class="pagination-info visible-lg-inline">当前' . $this->pageNo . ' - ' . ($this->allPage == $this->pageNo ? $this->total : ($this->pageNo * $this->pageSize)) . '/' . $this->total . ' 条</div>' . PHP_EOL;

            $pages .= '<ul class="pagination" >' . PHP_EOL;
            if ($this->pageNo > $this->showPageFigure) {
                $pages .= '<li><a href="' . $this->pageUrl . '1">«</a></li>' . PHP_EOL;
            }

            if ($this->pageNo > 1) {
                $pages .= '<li><a href="' . $this->pageUrl . max(($this->pageNo - 1), 1) . '">‹</a></li>' . PHP_EOL;
            }

            if ($this->pageList) {
                foreach ($this->pageList as $key => $value) {
                    $value == $this->pageNo ? $class = 'class="active"' : $class = '';
                    $pages .= '<li ' . $class . '><a href="' . $this->pageUrl . $value . '">' . $value . '</a></li>' . PHP_EOL;
                }
            }

            $pages .= '<li><a href="' . $this->pageUrl . min(($this->pageNo + 1), $this->allPage) . '">›</a></li>' . PHP_EOL;
            $pages .= '<li><a href="' . $this->pageUrl . $this->allPage . '">»</a></li>' . PHP_EOL;
            $pages .= '</ul>' . PHP_EOL;
            $pages .= $this->pageJump();
            $pages .= '<span class="pagination-goto visible-lg-inline">' . PHP_EOL;
            $pages .= '<input type="text" class="form-control w40 dh-page-go-value">' . PHP_EOL;
            $pages .= '<button class="btn btn-default dh-page-go" type="button" data-url="' . $this->pageUrl . '">GO</button>' . PHP_EOL;
            $pages .= '</span>' . PHP_EOL;
            $pages .= '</div>' . PHP_EOL;

            return $pages;
        }
    }

    public function loadPc()
    {
        if ($this->allPage > 1) {
            if (stripos($this->pageUrl, '?') === false) {
                $this->pageUrl = $this->pageUrl . '?pageNo=';
            } else {
                $this->pageUrl = $this->pageUrl . '&pageNo=';
            }

            $pages = '<div class="pull-left page-box">' . PHP_EOL;
            $pages .= '<div class="pagination-info hidden-sm hidden-xs">当前' . $this->pageNo . ' - ' . ($this->pageNo * $this->pageSize) . '/' . ($this->allPage * $this->pageSize) . ' 条</div>' . PHP_EOL;
            $pages .= '<ul class="pagination hidden-sm hidden-xs" >' . PHP_EOL;
            if ($this->pageNo > $this->showPageFigure) {
                $pages .= '<li><a href="' . $this->pageUrl . '1">«</a></li>' . PHP_EOL;
            }

            if ($this->pageNo > 1) {
                $pages .= '<li><a href="' . $this->pageUrl . max(($this->pageNo - 1), 1) . '">‹</a></li>' . PHP_EOL;
            }

            if ($this->pageList) {
                foreach ($this->pageList as $key => $value) {
                    $value == $this->pageNo ? $class = 'class="active"' : $class = '';
                    $pages .= '<li ' . $class . '><a href="' . $this->pageUrl . $value . '">' . $value . '</a></li>' . PHP_EOL;
                }
            }
            $pages .= '<li><a href="' . $this->pageUrl . min(($this->pageNo + 1), $this->allPage) . '">›</a></li>' . PHP_EOL;
            $pages .= '<li><a href="' . $this->pageUrl . $this->allPage . '">»</a></li>' . PHP_EOL;
            $pages .= '</ul>' . PHP_EOL;
            $pages .= '<span class="pagination-goto">' . PHP_EOL;
            $pages .= '</span>' . PHP_EOL;
            $pages .= '</div>' . PHP_EOL;

            return $pages;
        }
    }

    public function pc()
    {
        if ($this->allPage > 1) {
            if (stripos($this->pageUrl, '?') === false) {
                $this->pageUrl = $this->pageUrl . '?pageNo=';
            } else {
                $this->pageUrl = $this->pageUrl . '&pageNo=';
            }

            $pages = '<ul class="pagination hidden-sm hidden-xs" >' . PHP_EOL;

            $pages .= '<li><a href="' . $this->pageUrl . '1">首页</a></li>' . PHP_EOL;

            if ($this->pageNo > $this->showPageFigure) {
                $pages .= '<li><a href="' . $this->pageUrl . '1">«</a></li>' . PHP_EOL;
            }

            if ($this->pageNo > 1) {
                $pages .= '<li><a href="' . $this->pageUrl . max(($this->pageNo - 1), 1) . '">‹</a></li>' . PHP_EOL;
            }

            if ($this->pageList) {
                foreach ($this->pageList as $key => $value) {
                    $value == $this->pageNo ? $class = 'class="active"' : $class = '';

                    $pageUrl = $class != '' ? 'javascript:;' : $this->pageUrl . $value;

                    $pages .= '<li ' . $class . '><a href="' . $pageUrl . '">' . $value . '</a></li>' . PHP_EOL;
                }
            }
            $pages .= '<li><a href="' . $this->pageUrl . min(($this->pageNo + 1), $this->allPage) . '">›</a></li>' . PHP_EOL;
            $pages .= '<li><a href="' . $this->pageUrl . $this->allPage . '">»</a></li>' . PHP_EOL;
            $pages .= '</ul>' . PHP_EOL;
            $pages .= '<span class="pagination-goto">' . PHP_EOL;
            $pages .= '</span>' . PHP_EOL;
            // $pages .= '</div>' . PHP_EOL;

            return $pages;
        }
    }
}
