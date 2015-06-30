<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaHostTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'      => 'h.id',
            'host'    => 'h.object_name',
            'address' => 'h.address',
            'zone'    => 'z.object_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/host', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'host'    => $view->translate('Hostname'),
            'address' => $view->translate('Address'),
            'zone'    => $view->translate('Zone'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('h' => 'icinga_host'),
            $this->getColumns()
        )->joinLeft(
            array('z' => 'icinga_zone'),
            'h.zone_id = z.id',
            array()
        );

        return $db->fetchAll($query);
    }
}
