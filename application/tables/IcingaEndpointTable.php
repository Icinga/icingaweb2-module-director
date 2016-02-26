<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\IcingaObjectTable;

class IcingaEndpointTable extends IcingaObjectTable
{
    protected $searchColumns = array(
        'endpoint',
    );

    public function getColumns()
    {
        return array(
            'id'          => 'e.id',
            'endpoint'    => 'e.object_name',
            'object_type' => 'e.object_type',
            'host'        => "(CASE WHEN e.host IS NULL THEN NULL ELSE"
                           . " CONCAT(e.host || ':' || COALESCE(e.port, 5665)) END)",
            'zone'        => 'z.object_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/endpoint', array('name' => $row->endpoint));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'endpoint' => $view->translate('Endpoint'),
            'host'     => $view->translate('Host'),
            'zone'     => $view->translate('Zone'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('e' => 'icinga_endpoint'),
            array()
        )->joinLeft(
            array('z' => 'icinga_zone'),
            'e.zone_id = z.id',
            array()
        );

        return $query;
    }
}
