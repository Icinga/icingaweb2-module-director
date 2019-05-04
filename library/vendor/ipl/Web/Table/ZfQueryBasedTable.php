<?php

namespace dipl\Web\Table;

use Icinga\Data\Db\DbConnection;
use Icinga\Data\Filter\Filter;
use dipl\Db\Zf1\FilterRenderer;
use dipl\Db\Zf1\SelectPaginationAdapter;
use dipl\Html\DeferredText;
use dipl\Html\Html;
use dipl\Html\Link;
use dipl\Web\Widget\ControlsAndContent;
use dipl\Web\Url;
use LogicException;
use RuntimeException;
use Zend_Db_Adapter_Abstract as DbAdapter;

abstract class ZfQueryBasedTable extends QueryBasedTable
{
    /** @var DbConnection */
    private $connection;

    /** @var DbAdapter */
    private $db;

    private $query;

    private $paginationAdapter;

    private $search;

    public function __construct($db)
    {
        if ($db instanceof DbAdapter) {
            $this->db = $db;
        } elseif ($db instanceof DbConnection) {
            $this->connection = $db;
            $this->db = $db->getDbAdapter();
        } else {
            throw new LogicException(sprintf(
                'Unable to deal with %s db class',
                get_class($db)
            ));
        }
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
        if ($this->paginationAdapter === null) {
            $this->paginationAdapter = new SelectPaginationAdapter($this->getQuery());
        }

        return $this->paginationAdapter;
    }

    public function applyFilter(Filter $filter)
    {
        FilterRenderer::applyToQuery($filter, $this->getQuery());
        return $this;
    }

    public function hasSearch()
    {
        return $this->search !== null;
    }

    public function search($search)
    {
        if ($this->hasSearch()) {
            throw new RuntimeException(
                'It is not allowed to call search twice on this table'
            );
        }
        if (! empty($search)) {
            $this->search = $search;
            $query = $this->getQuery();
            $columns = $this->getSearchColumns();
            if (strpos($search, ' ') === false) {
                $filter = Filter::matchAny();
                foreach ($columns as $column) {
                    if (strpos($search, '*') === false) {
                        $filter->addFilter(Filter::expression($column, '=', "*$search*"));
                    } else {
                        $filter->addFilter(Filter::expression($column, '=', $search));
                    }
                }
            } else {
                $filter = Filter::matchAll();
                foreach (explode(' ', $search) as $s) {
                    $sub = Filter::matchAny();
                    foreach ($columns as $column) {
                        if (strpos($search, '*') === false) {
                            $sub->addFilter(Filter::expression($column, '=', "*$s*"));
                        } else {
                            $sub->addFilter(Filter::expression($column, '=', $s));
                        }
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
        return Html::tag('div', ['class' => 'sql-dump'], [
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
