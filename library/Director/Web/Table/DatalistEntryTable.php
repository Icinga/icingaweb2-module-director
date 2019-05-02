<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\DirectorDatalist;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;

class DatalistEntryTable extends ZfQueryBasedTable
{
    protected $datalist;

    protected $searchColumns = [
        'entry_name',
        'entry_value'
    ];

    public function setList(DirectorDatalist $list)
    {
        $this->datalist = $list;

        return $this;
    }

    public function getList()
    {
        return $this->datalist;
    }

    public function getColumns()
    {
        return  [
            'list_name'   => 'l.list_name',
            'list_id'     => 'le.list_id',
            'entry_name'  => 'le.entry_name',
            'entry_value' => 'le.entry_value',
        ];
    }

    public function renderRow($row)
    {
        return $this::tr([
            $this::td(Link::create($row->entry_name, 'director/data/listentry/edit', [
                'list'       => $row->list_name,
                'entry_name' => $row->entry_name,
            ])),
            $this::td($row->entry_value)
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return [
            'entry_name'    => $this->translate('Key'),
            'entry_value'   => $this->translate('Label'),
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['le' => 'director_datalist_entry'],
            $this->getColumns()
        )->join(
            ['l' => 'director_datalist'],
            'l.id = le.list_id',
            []
        )->where(
            'le.list_id = ?',
            $this->getList()->id
        )->order('le.entry_name ASC');
    }
}
