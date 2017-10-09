<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\IcingaService;
use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;

class IcingaAppliedServiceTable extends ZfQueryBasedTable
{
    protected $service;

    protected $searchColumns = array(
        'service',
    );

    public function setService(IcingaService $service)
    {
        $this->service = $service;
        return $this;
    }

    public function renderRow($row)
    {
        return $this::row([
            new Link($row->service, 'director/service', ['id' => $row->id])
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return [$this->translate('Servicename')];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            array('s' => 'icinga_service'),
            array()
        )->joinLeft(
            array('si' => 'icinga_service_inheritance'),
            's.id = si.service_id',
            array()
        )->where(
            'si.parent_service_id = ?',
            $this->service->id
        )->where('s.object_type = ?', 'apply');
    }
}
