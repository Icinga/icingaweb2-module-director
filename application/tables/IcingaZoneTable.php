<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaZoneTable extends QuickTable
{
    public function getColumns()
    {
        if ($this->connection()->getDbType() === 'pgsql') {
            $endpoints = "ARRAY_TO_STRING(ARRAY_AGG(e.object_name), ', ')";
        } else {
            $endpoints = "GROUP_CONCAT(e.object_name ORDER BY e.object_name SEPARATOR ', ')";
        }

        return array(
            'id'        => 'z.id',
            'zone'      => 'z.object_name',
            'endpoints' => $endpoints,
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/zone', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'zone'      => $view->translate('Zone'),
            'endpoints' => $view->translate('Endpoints'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('z' => 'icinga_zone'),
            $this->getColumns()
        )->joinLeft(
            array('e' => 'icinga_endpoint'),
            'z.id = e.zone_id',
            array()
        )->group('z.id');

        return $db->fetchAll($query);
    }
}
