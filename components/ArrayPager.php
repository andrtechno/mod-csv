<?php

namespace panix\mod\csv\components;

use Yii;
use panix\engine\CMS;
use yii\base\InvalidConfigException;
use yii\data\Pagination;

/**
 * Class ArrayPager
 */
class ArrayPager
{

    /**
     * @var Pagination the pagination object that this pager is associated with.
     * You must set this property in order to make LinkPager work.
     */
    public $pagination;


    /**
     * @var bool Hide widget when only one page exist.
     */
    public $hideOnSinglePage = true;


    /**
     * Initializes the pager.
     */
    public function __construct($pagination)
    {

        if ($pagination === null) {
            throw new InvalidConfigException('The "pagination" property must be set.');
        }
        $this->pagination = $pagination;
    }


    /**
     * Renders the page buttons.
     * @return array the rendering result
     */
    public function list()
    {
        $pageCount = $this->pagination->getPageCount();
        if ($pageCount < 2 && $this->hideOnSinglePage) {
            return [];
        }

        $buttons = [];
        $currentPage = $this->pagination->getPage();
        $pageSize = $this->pagination->pageSize;

        // internal pages

        foreach ($this->getPageRange() as $i => $page) {
            $buttons[] = [
                'page' => $page + 1,
                'offset' => $i * $pageSize,
            ];
        }
        return $buttons;
    }


    /**
     * @return array the begin and end pages that need to be displayed.
     */
    protected function getPageRange()
    {
        $pageCount = $this->pagination->getPageCount();

return range(0, $pageCount);
      //  return range(0, count(array_chunk(range(0, $pageCount), $this->pagination->pageSize, false)));
    }


}