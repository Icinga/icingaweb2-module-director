<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaServiceSetServiceTable extends QuickTable
{
    protected $set;

    protected $searchColumns = array(
        'service',
    );

    public function getColumns()
    {
        return array(
            'id'             => 's.id',
            'service_set_id' => 's.service_set_id',
            'service_set'    => 'ss.object_name',
            'service'        => 's.object_name',
            'object_type'    => 's.object_type',
        );
    }

    public function setServiceSet(IcingaServiceSet $set)
    {
        $this->set = $set;
        return $this;
    }

    protected function getActionUrl($row)
    {
        $params = array(
            'name' => $row->service,
            'set'  => $row->service_set
        );
    
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
            array('ss' => 'icinga_service_set'),
            'ss.id = s.service_set_id',
            array()
        )->order('s.object_name');

        return $query;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where(
            's.service_set_id = ?',
            $this->set->id
        );
    }
}
