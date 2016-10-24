<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaServiceTable extends QuickTable
{
    protected $searchColumns = array(
        'service',
    );

    public function getColumns()
    {
        return array(
            'id' => 's.id',
            'service' => 's.object_name',
            'object_type' => 's.object_type',
            'check_command_id' => 's.check_command_id',
        );
    }

    protected function getActionUrl($row)
    {
        // TODO: Remove once we got a separate apply table
        if ($row->object_type === 'apply') {
            $params['id'] = $row->id;
        } else {
            $params = array('name' => $row->service);

        }

        return $this->url('director/service/edit', $params);
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
        $query = $this->db()->select()->from(
            array('s' => 'icinga_service'),
            array()
        );

        return $query;
    }

    protected function appliedOnes($id)
    {
        $db = $this->db();
        $query = $db->select()->from(
            array('s' => 'icinga_service'),
            array(
                'id'          => 's.id',
                'object_name' => 's.object_name',
                'assign_filter' => 's.assign_filter',
            )
        )->join(
            array('i' => 'icinga_service_inheritance'),
            'i.service_id = s.id',
            array()
        )->where('i.parent_service_id = ?', $id)
        ->where('s.object_type = ?', 'apply')
        ->where('s.service_set_id IS NULL');

        return $db->fetchAll($query);
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where(
            's.object_type IN (?)',
            array('template')
        )->order('CASE WHEN s.check_command_id IS NULL THEN 1 ELSE 0 END')
            ->order('s.object_name');
    }
}
