<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaHostGroupMemberTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'hostgroup_id'          => 'hg.id',
            'host_id'               => 'h.id',
            'hostgroup'             => 'hg.object_name',
            'host'                  => 'h.object_name'
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/object/hostgroupmember', array(
            'hostgroup_id' => $row->hostgroup_id,
            'host_id'      => $row->host_id,
        ));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'hostgroup' => $view->translate('Hostgroup'),
            'host'      => $view->translate('Member'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('hg' => 'icinga_hostgroup'),
            $this->getColumns()
        )->join(
            array('hgh' => 'icinga_hostgroup_host'),
            'hgh.hostgroup_id = hg.id',
            array()
        )->join(
            array('h' => 'icinga_host'),
            'hgh.host_id = h.id',
            array()
        );

        return  $db->fetchAll($query);
    }
}
