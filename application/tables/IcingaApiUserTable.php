<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\IcingaObjectTable;

class IcingaApiUserTable extends IcingaObjectTable
{
    protected $searchColumns = array(
        'object_name',
    );

    public function getColumns()
    {
        return array(
            'id'          => 'o.id',
            'object_name' => 'o.object_name',
            'object_type' => 'o.object_type',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/apiuser', array('name' => $row->object_name));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'object_name' => $view->translate('User'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('o' => 'icinga_apiuser'),
            array()
        );

        return $query;
    }
}
