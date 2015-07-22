<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class DatalistEntryTable extends QuickTable
{
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
        return $this->url('director/datalistentry/edit', array(
            'list_id'    => $row->list_id,
            'entry_name' => $row->entry_name,
        ));
    }

    public function getListId()
    {
        return $this->view()->lastId;
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'entry_name'    => $view->translate('Name'),
            'entry_value'   => $view->translate('Value'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('l' => 'director_datalist_entry'),
            $this->getColumns()
        )->where('l.list_id = ?', $this->getListId())->order('l.entry_name ASC');

        return $db->fetchAll($query);
    }
}
