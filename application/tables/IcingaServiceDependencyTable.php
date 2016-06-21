<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaServiceDependencyTable extends QuickTable
{
    protected $host;
    protected $service;

    protected $searchColumns = array(
        'dependency',
    );

    public function getColumns()
    {
        return array(
            'id'          => 'd.id',
            'child_host_id'     => 'd.child_host_id',
            'child_service_id'     => 'd.child_service_id',
            'host'        => 'h.object_name',
            'service'        => 's.object_name',
            'dependency'     => 'd.object_name',
            'object_type' => 'd.object_type',
        );
    }

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
    }

    public function setService(IcingaService $service)
    {
        $this->service = $service;
        return $this;
    }


    protected function getActionUrl($row)
    {
        if ($row->object_type === 'apply') {
            $params['id'] = $row->id;
        } else {
            $params = array('name' => $row->dependency);
        }

        return $this->url('director/dependency', $params);
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'dependency' => $view->translate('Dependency Name'),
        );
    }

    public function getUnfilteredQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('d' => 'icinga_dependency'),
            array()
        )->joinLeft(
            array('h' => 'icinga_host'),
            'h.id = d.child_host_id',
            array()
        )->joinLeft(
            array('s' => 'icinga_service'),
            's.id = d.child_service_id',
            array()
        );

        return $query;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where(
            'd.child_service_id = ?',
            $this->service->id
        );
    }
}
