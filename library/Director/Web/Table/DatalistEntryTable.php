<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\DirectorDatalist;
use ipl\Html\Link;
use ipl\Web\Table\ZfQueryBasedTable;

class DatalistEntryTable extends ZfQueryBasedTable
{
    protected $datalist;

    protected $searchColumns = array(
        'entry_name',
        'entry_value'
    );

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
        return array(
            'list_id'       => 'l.list_id',
            'entry_name'    => 'l.entry_name',
            'entry_value'   => 'l.entry_value',
        );
    }

    public function renderRow($row)
    {
        return $this::tr([
            $this::td(Link::create($row->entry_name, 'director/data/listentry/edit', [
                'list_id'    => $row->list_id,
                'entry_name' => $row->entry_name,
            ])),
            $this::td($row->entry_value)
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return array(
            'entry_name'    => $this->translate('Key'),
            'entry_value'   => $this->translate('Label'),
        );
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            array('l' => 'director_datalist_entry'),
            $this->getColumns()
        )->where(
            'l.list_id = ?',
            $this->getList()->id
        )->order('l.entry_name ASC');
    }
}
