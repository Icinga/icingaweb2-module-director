<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\IcingaObjectTable;

class IcingaServiceSetTable extends IcingaObjectTable
{
    protected $searchColumns = array(
        'name',
    );

    public function getColumns()
    {
        return array(
            'id'             => 'sset.id',
            'name'           => 'sset.object_name',
            'object_type'    => 'sset.object_type',
            'assign_filter'  => 'sset.assign_filter',
            'description'    => 'sset.description',
            'count_hosts'    => 'count(distinct ssetobj.id)',
            'count_services' => 'count(distinct s.id)',
        );
    }

    protected function getRowClasses($row)
    {
        $class = parent::getRowClasses($row);

        if ($row->object_type === 'template' && $row->assign_filter !== null) {
            $class = 'icinga-apply';
        }

        return $class;
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'name' => $view->translate('Service set'),
            'count_services' => $view->translate('# Services'),
            'count_hosts' => $view->translate('# Hosts'),
        );
    }

    protected function getActionUrl($row)
    {
        if ($row->object_type === 'apply') {
            $params['id'] = $row->id;
        } else {
            $params = array('name' => $row->name);
        }

        return $this->url('director/serviceset', $params);
    }

    protected function getUnfilteredQuery()
    {
        return $this->db()->select()->from(
            array('sset' => 'icinga_service_set'),
            array()
        )->joinLeft(
            array('ssih' => 'icinga_service_set_inheritance'),
            'ssih.parent_service_set_id = sset.id',
            array()
        )->joinLeft(
            array('ssetobj' => 'icinga_service_set'),
            'ssetobj.id = ssih.service_set_id',
            array()
        )->joinLeft(
            array('s' => 'icinga_service'),
            's.service_set_id = sset.id',
            array()
        )->group('sset.id')
        ->where('sset.object_type = ?', 'template')->order('sset.object_name');
    }

    public function count()
    {
        $db = $this->db();
        $sub = clone($this->getBaseQuery());
        $sub->columns($this->getColumns());
        $this->applyFiltersToQuery($sub);
        $query = $db->select()->from(
            array('sub' => $sub),
            'COUNT(*)'
        );

        return $db->fetchOne($query);
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}
