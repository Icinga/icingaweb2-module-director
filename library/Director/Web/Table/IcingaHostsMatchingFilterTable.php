<?php

namespace Icinga\Module\Director\Web\Table;

use gipfl\IcingaWeb2\Data\SimpleQueryPaginationAdapter;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\QueryBasedTable;
use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Data\Filter\Filter;
use Icinga\Data\SimpleQuery;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Resolver\IcingaHostObjectResolver;

class IcingaHostsMatchingFilterTable extends QueryBasedTable
{
    protected $searchColumns = [
        'object_name',
    ];

    /** @var ArrayDatasource */
    protected $dataSource;

    public static function load(Filter $filter, Db $db)
    {
        $table = new static();
        $table->dataSource = new ArrayDatasource(
            (new IcingaHostObjectResolver($db->getDbAdapter()))
                ->fetchObjectsMatchingFilter($filter)
        );

        return $table;
    }

    public function renderRow($row)
    {
        return $this::row([
            Link::create(
                $row->object_name,
                'director/host',
                ['name' => $row->object_name]
            )
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Hostname'),
        ];
    }

    protected function getPaginationAdapter()
    {
        return new SimpleQueryPaginationAdapter($this->getQuery());
    }

    public function getQuery()
    {
        return $this->prepareQuery();
    }

    protected function fetchQueryRows()
    {
        return $this->dataSource->fetchAll($this->getQuery());
    }

    protected function prepareQuery()
    {
        return new SimpleQuery($this->dataSource, ['object_name']);
    }
}
