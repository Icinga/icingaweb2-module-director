<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class DatalistTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'        => 'l.id',
            'list_name' => 'l.list_name',
            'owner'     => 'l.owner',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/show/datalist', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'list_name' => $view->translate('List name'),
            'owner'     => $view->translate('Owner'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('l' => 'director_datalist'),
            $this->getColumns()
        )->order('list_name ASC');

        return $db->fetchAll($query);
    }
}
