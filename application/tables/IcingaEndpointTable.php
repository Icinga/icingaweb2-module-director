<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaEndpointTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'       => 'e.id',
            'endpoint' => 'e.object_name',
            'address'  => 'e.address',
            'zone'     => 'z.object_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/endpoint', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'endpoint' => $view->translate('Endpoint'),
            'address'  => $view->translate('Address'),
            'zone'     => $view->translate('Zone'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('e' => 'icinga_endpoint'),
            $this->getColumns()
        )->joinLeft(
            array('z' => 'icinga_zone'),
            'e.zone_id = z.id',
            array()
        );

        return $db->fetchAll($query);
    }
}
