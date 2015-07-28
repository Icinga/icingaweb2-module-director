<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Data\Limitable;
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
            'parents' => "GROUP_CONCAT(ih.object_name ORDER BY hi.weight SEPARATOR ', ')"
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/host', array('name' => $row->host));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'host'    => $view->translate('Hostname'),
            'address' => $view->translate('Address'),
            'zone'    => $view->translate('Zone'),
            'parents' => $view->translate('Imports'),
        );
    }

    protected function getUnfilteredQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('h' => 'icinga_host'),
            array()
        )->joinLeft(
            array('z' => 'icinga_zone'),
            'h.zone_id = z.id',
            array()
        )->joinLeft(
            array('hi' => 'icinga_host_inheritance'),
            'hi.host_id = h.id',
            array()
        )->joinLeft(
            array('ih' => 'icinga_host'),
            'hi.parent_host_id = ih.id',
            array()
        )->group('h.id')
        ->order('h.object_name');

        return $query;
    }

    public function count()
    {
        $db = $this->connection()->getConnection();
        $sub = clone($this->getBaseQuery());
        $sub->columns($this->getColumns());
        $query = $db->select()->from(
            array('sub' => $sub),
            'COUNT(*)'
        );

        return $db->fetchOne($query);
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where('h.object_type = ?', 'object');
    }
}
