<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaAppliedServiceTable extends QuickTable
{
    protected $service;

    protected $searchColumns = array(
        'service',
    );

    public function getColumns()
    {
        return array(
            'id'           => 's.id',
            'service'      => 's.object_name',
            'object_type'  => 's.object_type',
            'display_name' => 's.display_name',
        );
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
        }

        return $this->url('director/service', $params);
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'service' => $view->translate('Servicename'),
        );
    }

    public function getUnfilteredQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('s' => 'icinga_service'),
            array()
        )->joinLeft(
            array('si' => 'icinga_service_inheritance'),
            's.id = si.service_id',
            array()
        );

        return $query;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where(
            'si.parent_service_id = ?',
            $this->service->id
        )->where('s.object_type = ?', 'apply');
    }
}
