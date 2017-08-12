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
use ipl\Web\Widget\ControlsAndContent;
use ipl\Web\Url;

abstract class ZfQueryBasedTable extends QueryBasedTable
{
    /** @var DbConnection */
    private $connection;

    /** @var \Zend_Db_Adapter_Abstract */
    private $db;

    private $query;

    public function __construct(DbConnection $connection)
    {
        $this->connection = $connection;
        $this->db = $connection->getDbAdapter();
    }

    public static function show(ControlsAndContent $controller, DbConnection $db)
    {
        $table = new static($db);
        $table->renderTo($controller);
    }

    public function getCountQuery()
    {
        return $this->getPaginationAdapter()->getCountQuery();
    }

    protected function getPaginationAdapter()
    {
        return new SelectPaginationAdapter($this->getQuery());
    }

    public function applyFilter(Filter $filter)
    {
        FilterRenderer::applyToQuery($filter, $this->getQuery());
        return $this;
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

    protected function fetchQueryRows()
    {
        return $this->db->fetchAll($this->getQuery());
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
}
