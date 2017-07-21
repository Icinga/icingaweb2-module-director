<?php

namespace ipl\Web\Table;

use Icinga\Data\Db\DbConnection;
use Icinga\Data\Filter\Filter;
use ipl\Db\Zf1\FilterRenderer;
use ipl\Db\Zf1\SelectPaginationAdapter;
use ipl\Html\Container;
use ipl\Html\DeferredText;
use ipl\Html\Html;
use ipl\Html\Link;
use ipl\Html\Table;
use ipl\Translation\TranslationHelper;
use ipl\Web\Widget\ControlsAndContent;
use ipl\Web\Widget\Paginator;
use ipl\Web\Table\Extension\QuickSearch;
use ipl\Web\Url;
use Zend_Db_Exception as Expr;

abstract class ZfQueryBasedTable extends Table
{
    use TranslationHelper;
    use QuickSearch;

    protected $defaultAttributes = [
        'class' => ['common-table', 'table-row-selectable'],
        'data-base-target' => '_next',
    ];

    /** @var DbConnection */
    private $connection;

    /** @var \Zend_Db_Adapter_Abstract */
    private $db;

    private $query;

    private $fetchedRows;

    protected $lastDay;

    private $isUsEnglish;

    protected $searchColumns = [];

    public function __construct(DbConnection $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public function getCountQuery()
    {
        return $this->getPaginationAdapter()->getCountQuery();
    }

    protected function getPaginationAdapter()
    {
        return new SelectPaginationAdapter($this->getQuery());
    }

    public function getPaginator(Url $url)
    {
        return new Paginator(
            $this->getPaginationAdapter(),
            $url
        );
    }

    protected function getSearchColumns()
    {
        return $this->searchColumns;
    }

    protected function search($search)
    {
        if (! empty($search)) {
            $query = $this->getQuery();
            $columns = $this->getSearchColumns();
            if (strpos($search, ' ') === false) {
                $filter = Filter::matchAny();
                foreach ($columns as $column) {
                    $filter->addFilter(Filter::expression($column, '=', "*$search*"));
                }
            } else {
                $filter = Filter::matchAll();
                foreach (explode(' ', $search) as $s) {
                    $sub = Filter::matchAny();
                    foreach ($columns as $column) {
                        $sub->addFilter(Filter::expression($column, '=', "*$s*"));
                    }
                    $filter->addFilter($sub);
                }
            }

            FilterRenderer::applyToQuery($filter, $query);
        }

        return $this;
    }

    abstract protected function prepareQuery();

    public function renderContent()
    {
        if (count($this->getColumnsToBeRendered())) {
            $this->generateHeader();
        }
        $this->fetchRows();

        return parent::renderContent();
    }

    protected function splitByDay($timestamp)
    {
        $this->renderDayIfNew((int) $timestamp);
    }

    protected function fetchRows()
    {
        foreach ($this->fetch() as $row) {
            // Hint: do not fetch the body first, the row might want to replace it
            $tr = $this->renderRow($row);
            $this->body()->add($tr);
        }
    }


    protected function isUsEnglish()
    {
        if ($this->isUsEnglish === null) {
            $this->isUsEnglish = in_array(setlocale(LC_ALL, 0), array('en_US.UTF-8', 'C'));
        }

        return $this->isUsEnglish;
    }

    /**
     * @param  int $timestamp
     * @return string
     */
    protected function renderDayIfNew($timestamp)
    {
        if ($this->isUsEnglish()) {
            $day = date('l, jS F Y', $timestamp);
        } else {
            $day = strftime('%A, %e. %B, %Y', $timestamp);
        }

        if ($this->lastDay === $day) {
            return;
        }

        $this->nextHeader()->add(
            $this::th($day, ['colspan' => 2])->addAttributes(['class' => 'table-header-day'])
        );

        $this->lastDay = $day;
        $this->nextBody();
    }

    public function fetch()
    {
        $rows = $this->db->fetchAll(
            $this->getQuery()
        );

        $this->fetchedRows = count($rows);

        return $rows;
    }

    public function connection()
    {
        return $this->connection;
    }

    public function db()
    {
        return $this->db;
    }

    /**
     * @return \Zend_Db_Select
     */
    public function getQuery()
    {
        if ($this->query === null) {
            $this->query = $this->prepareQuery();
        }

        return $this->query;
    }

    public function dumpSqlQuery(Url $url)
    {
        $self = $this;
        return Container::create(['class' => 'sql-dump'], [
            Link::create('[ close ]', $url->without('format')),
            Html::tag('h3', null, $this->translate('SQL Query')),
            Html::tag('pre', null, new DeferredText(
                function () use ($self) {
                    return wordwrap($self->getQuery());
                }
            )),
            Html::tag('h3', null, $this->translate('Count Query')),
            Html::tag('pre', null, new DeferredText(
                function () use ($self) {
                    return wordwrap($self->getCountQuery());
                }
            )),
        ]);
    }

    public static function show(ControlsAndContent $controller, DbConnection $db)
    {
        $table = new static($db);
        $table->renderTo($controller);
    }

    protected function initializeOptionalQuickSearch(ControlsAndContent $controller)
    {
        $columns = $this->getSearchColumns();
        if (! empty($columns)) {
            $this->search(
                $this->getQuickSearch(
                    $controller->controls(),
                    $controller->url()
                )
            );
        }
    }

    /**
     * @param ControlsAndContent $controller
     * @return $this
     */
    public function renderTo(ControlsAndContent $controller)
    {
        $url = $controller->url();
        $c = $controller->content();
        $paginator = $this->getPaginator($url);
        $this->initializeOptionalQuickSearch($controller);
        $controller->actions()->add($paginator);
        $c->add($this);

        if ($url->getParam('format') === 'sql') {
            $c->prepend($this->dumpSqlQuery($url));
        }

        return $this;
    }
}
