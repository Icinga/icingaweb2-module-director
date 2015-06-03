<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaServicegroupMemberTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'servicegroup_id'          => 'sg.id',
            'service_id'               => 's.id',
            'servicegroup'             => 'sg.object_name',
            'service'                  => 's.object_name'
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/object/servicegroupmember', array(
            'servicegroup_id' => $row->servicegroup_id,
            'service_id'      => $row->service_id,
        ));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'servicegroup' => $view->translate('Servicegroup'),
            'service'      => $view->translate('Member'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('sg' => 'icinga_servicegroup'),
            $this->getColumns()
        )->join(
            array('sgs' => 'icinga_servicegroup_service'),
            'sgs.servicegroup_id = sg.id',
            array()
        )->join(
            array('s' => 'icinga_service'),
            'sgs.service_id = s.id',
            array()
        );

        return  $db->fetchAll($query);
    }
}
