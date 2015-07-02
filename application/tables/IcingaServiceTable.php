<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaServiceTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'      => 's.id',
            'service' => 's.object_name',
            'zone'    => 'z.object_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/service', array('name' => $row->service));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'service' => $view->translate('Servicename'),
            'zone'    => $view->translate('Zone'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('s' => 'icinga_service'),
            $this->getColumns()
        )->joinLeft(
            array('z' => 'icinga_zone'),
            's.zone_id = z.id',
            array()
        );

        return $db->fetchAll($query);
    }
}
