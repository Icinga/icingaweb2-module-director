<?php

namespace ipl\Web\Component;

use Icinga\Exception\ProgrammingError;
use ipl\Data\Paginatable;
use ipl\Html\BaseElement;
use ipl\Html\Html;
use ipl\Html\Icon;
use ipl\Html\Link;
use ipl\Translation\TranslationHelper;
use ipl\Web\Url;

class Paginator extends BaseElement
{
    use TranslationHelper;

    protected $tag = 'div';

    protected $defaultAttributes = [
        'class' => 'pagination-control',
        'role'  => 'navigation',
    ];

    /** @var Paginatable The query the paginator widget is created for */
    protected $query;

    /** @var int */
    protected $pageCount;

    /** @var int */
    protected $currentCount;

    /** @var Url */
    protected $url;

    /** @var string */
    protected $pageParam;

    /** @var string */
    protected $perPageParam;

    /** @var int */
    protected $totalCount;

    /** @var int */
    protected $defaultItemCountPerPage = 25;

    public function __construct(
        Paginatable $query,
        Url $url,
        $pageParameter = 'page',
        $perPageParameter = 'limit'
    ) {
        $this->query = $query;
        $this->setPageParam($pageParameter);
        $this->setPerPageParam($perPageParameter);
        $this->setUrl($url);
    }

    public function setItemsPerPage($count)
    {
        // TODO: this should become setOffset once available
        $query = $this->getQuery();
        $query->setLimit($count);

        return $this;
    }

    protected function setPageParam($pageParam)
    {
        $this->pageParam = $pageParam;
        return $this;
    }

    protected function setPerPageParam($perPageParam)
    {
        $this->perPageParam = $perPageParam;
        return $this;
    }

    public function getPageParam()
    {
        return $this->pageParam;
    }

    public function getPerPageParam()
    {
        return $this->perPageParam;
    }

    public function getCurrentPage()
    {
        $query = $this->getQuery();
        if ($query->hasOffset()) {
            return ($query->getOffset() / $this->getItemsPerPage()) + 1;
        } else {
            return 1;
        }
    }

    protected function setCurrentPage($page)
    {
        $page = (int) $page;
        $this->currentPage = $page;
        $offset = $this->firstRowOnPage($page) - 1;
        if ($page > 1) {
            $query = $this->getQuery();
            $query->setOffset($offset);
        }
    }

    public function getPageCount()
    {
        if ($this->pageCount === null) {
            $this->pageCount = (int) ceil($this->getTotalItemCount() / $this->getItemsPerPage());
        }

        return $this->pageCount;
    }

    protected function getItemsPerPage()
    {
        $limit = $this->getQuery()->getLimit();
        if ($limit === null) {
            throw new ProgrammingError('Something went wrong, got no limit when there should be one');
        } else {
            return $limit;
        }
    }

    public function getTotalItemCount()
    {
        if ($this->totalCount === null) {
            $this->totalCount = count($this->getQuery());
        }

        return $this->totalCount;
    }

    public function getPrevious()
    {
        if ($this->hasPrevious()) {
            return $this->getCurrentPage() - 1;
        } else {
            return null;
        }
    }

    public function hasPrevious()
    {
        return $this->getCurrentPage() > 1;
    }

    public function getNext()
    {
        if ($this->hasNext()) {
            return $this->getCurrentPage() + 1;
        } else {
            return null;
        }
    }

    public function hasNext()
    {
        return $this->getCurrentPage() < $this->getPageCount();
    }

    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Returns an array of "local" pages given the page count and current page number
     *
     * @return array
     */
    protected function getPages()
    {
        $page = $this->getPageCount();
        $current = $this->getCurrentPage();

        $range = [];

        if ($page < 10) {
            // Show all pages if we have less than 10
            for ($i = 1; $i < 10; $i++) {
                if ($i > $page) {
                    break;
                }

                $range[$i] = $i;
            }
        } else {
            // More than 10 pages:
            foreach ([1, 2] as $i) {
                $range[$i] = $i;
            }

            if ($current < 6) {
                // We are on page 1-5 from
                for ($i = 1; $i <= 7; $i++) {
                    $range[$i] = $i;
                }
            } else {
                // Current page > 5
                $range[] = '…';

                if (($page - $current) < 5) {
                    // Less than 5 pages left
                    $start = 5 - ($page - $current);
                } else {
                    $start = 1;
                }

                for ($i = $current - $start; $i < ($current + (4 - $start)); $i++) {
                    if ($i > $page) {
                        break;
                    }

                    $range[$i] = $i;
                }
            }

            if ($current < ($page - 2)) {
                $range[] = '…';
            }

            foreach ([$page - 1, $page] as $i) {
                $range[$i] = $i;
            }
        }

        if (empty($range)) {
            $range[] = 1;
        }

        return $range;
    }

    public function getDefaultItemCountPerPage()
    {
        return $this->defaultItemCountPerPage;
    }

    public function setDefaultItemCountPerPage($count)
    {
        $this->defaultItemCountPerPage = (int) $count;
        return $this;
    }

    public function setUrl(Url $url)
    {
        $page = (int) $url->shift($this->getPageParam());
        $perPage = (int) $url->getParam($this->getPerPageParam());
        if ($perPage > 0) {
            $this->setItemsPerPage($perPage);
        } else {
            $this->setItemsPerPage($this->getDefaultItemCountPerPage());
        }
        if ($page > 0) {
            $this->setCurrentPage($page);
        }

        $this->url = $url;

        return $this;
    }

    public function getUrl()
    {
        if ($this->url === null) {
            $this->setUrl(Url::fromRequest());
        }

        return $this->url;
    }

    public function getPreviousLabel()
    {
        return $this->getLabel($this->getCurrentPage() - 1);
    }

    protected function getNextLabel()
    {
        return $this->getLabel($this->getCurrentPage() + 1);
    }

    protected function getLabel($page)
    {
        return sprintf(
            $this->translate('Show rows %u to %u of %u'),
            $this->firstRowOnPage($page),
            $this->lastRowOnPage($page),
            $this->getTotalItemCount()
        );
    }

    protected function renderPrevious()
    {
        return Html::tag('li', [
            'class' => 'nav-item'
        ], Link::create(
            Icon::create('angle-double-left'),
            $this->makeUrl($this->getPrevious()),
            null,
            [
                'title' => $this->getPreviousLabel(),
                'class' => 'previous-page'
            ]
        ));
    }

    protected function renderNoPrevious()
    {
        return $this->renderDisabled(Html::tag('span', [
            'class' => 'previous-page'
        ], [
            $this->srOnly($this->translate('Previous page')),
            Icon::create('angle-double-left')
        ]));
    }

    protected function renderNext()
    {
        return Html::tag('li', [
            'class' => 'nav-item'
        ], Link::create(
            Icon::create('angle-double-right'),
            $this->makeUrl($this->getNext()),
            null,
            [
                'title' => $this->getNextLabel(),
                'class' => 'next-page'
            ]
        ));
    }

    protected function renderNoNext()
    {
        return $this->renderDisabled(Html::tag('span', [
            'class' => 'previous-page'
        ], [
            $this->srOnly($this->translate('Next page')),
            Icon::create('angle-double-right')
        ]));
    }

    protected function renderDots()
    {
        return $this->renderDisabled(Html::tag('span', null, '…'));
    }

    protected function renderInnerPages()
    {
        $pages = [];
        $current = $this->getCurrentPage();

        foreach ($this->getPages() as $page) {
            if ($page === '…') {
                $pages[] = $this->renderDots();
            } else {
                $pages[] = Html::tag(
                    'li',
                    $page === $current ? ['class' => 'active'] : null,
                    $this->makeLink($page)
                );
            }
        }

        return $pages;
    }

    protected function lastRowOnPage($page)
    {
        $perPage = $this->getItemsPerPage();
        $total = $this->getTotalItemCount();
        $last = $page * $perPage;
        if ($last > $total) {
            $last = $total;
        }

        return $last;
    }

    protected function firstRowOnPage($page)
    {
        return ($page - 1) * $this->getItemsPerPage() + 1;
    }

    protected function makeLink($page)
    {
        return Link::create(
            $page,
            $this->makeUrl($page),
            null,
            ['title' => $this->getLabel($page)]
        );
    }

    protected function makeUrl($page)
    {
        if ($page) {
            return $this->getUrl()->with('page', $page);
        } else {
            return $this->getUrl();
        }
    }

    protected function srOnly($content)
    {
        return Html::tag('span', ['class' => 'sr-only'], $content);
    }

    protected function renderDisabled($content)
    {
        return Html::tag('li', [
            'class' => ['nav-item', 'disabled'],
            'aria-hidden' => 'true'
        ], $content);
    }

    protected function renderList()
    {
        return Html::tag(
            'ul',
            ['class' => ['nav', 'tab-nav']],
            [
                $this->hasPrevious() ? $this->renderPrevious() : $this->renderNoPrevious(),
                $this->renderInnerPages(),
                $this->hasNext() ? $this->renderNext() : $this->renderNoNext()
            ]
        );
    }

    public function renderContent()
    {
        $this->add([
            $this->renderScreenReaderHeader(),
            $this->renderList()
        ]);

        return parent::renderContent();
    }

    protected function renderScreenReaderHeader()
    {
        return Html::tag('h2', [
            // 'id' => $this->protectId('pagination') -> why?
            'class'     => 'sr-only',
            'tab-index' => '-1'
        ], $this->translate('Pagination'));
    }

    public function render()
    {
        if ($this->getPageCount() < 2) {
            return '';
        } else {
            return parent::render();
        }
    }
}
