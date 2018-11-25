<?php

namespace Icinga\Module\Director\Web\Table;

use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;

class DatalistTable extends ZfQueryBasedTable
{
    protected $searchColumns = ['list_name'];

    public function getColumns()
    {
        return [
            'id'        => 'l.id',
            'list_name' => 'l.list_name',
        ];
    }

    public function renderRow($row)
    {
        return $this::tr($this::td(Link::create(
            $row->list_name,
            'director/data/listentry',
            array('list' => $row->list_name)
        )));
    }

    public function getColumnsToBeRendered()
    {
        return [$this->translate('List name')];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['l' => 'director_datalist'],
            $this->getColumns()
        )->order('list_name ASC');
    }
}
