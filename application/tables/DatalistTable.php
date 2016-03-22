<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class DatalistTable extends QuickTable
{
    protected $searchColumns = array(
        'list_name',
    );

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
        return $this->url(
            'director/data/listentry',
            array('list_id' => $row->id)
        );
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'list_name' => $view->translate('List name'),
            'owner'     => $view->translate('Owner'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('l' => 'director_datalist'),
            array()
        )->order('list_name ASC');

        return $query;
    }
}
