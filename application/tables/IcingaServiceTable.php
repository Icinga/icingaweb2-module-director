<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\IcingaObjectTable;

class IcingaServiceTable extends IcingaObjectTable
{
    protected $searchColumns = array(
        'service',
        'host'
    );

    public function getColumns()
    {
        return array(
            'id'          => 's.id',
            'host_id'     => 's.host_id',
            'host'        => 'h.object_name',
            'service'     => 's.object_name',
            'object_type' => 's.object_type',
        );
    }

    protected function getActionUrl($row)
    {
        $params = array('name' => $row->service);
        if ($row->host !== null) {
            $params['host'] = $row->host;
        }
        return $this->url('director/service', $params);
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'service' => $view->translate('Servicename'),
            'host'    => $view->translate('Host'),
        );
    }

    public function getUnfilteredQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('s' => 'icinga_service'),
            array()
        )->joinLeft(
            array('h' => 'icinga_host'),
            'h.id = s.host_id',
            array()
        );

        return $query;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where('s.object_type = ?', 'object');
    }
}
