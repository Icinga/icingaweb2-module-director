<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Web\Table\QuickTable;

class DatalistEntryTable extends QuickTable
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

    protected function getActionUrl($row)
    {
        return $this->url('director/data/listentry/edit', array(
            'list_id'    => $row->list_id,
            'entry_name' => $row->entry_name,
        ));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'entry_name'    => $view->translate('Key'),
            'entry_value'   => $view->translate('Label'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('l' => 'director_datalist_entry'),
            array()
        )->where(
            'l.list_id = ?',
            $this->getList()->id
        )->order('l.entry_name ASC');

        return $query;
    }
}
