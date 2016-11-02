<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\IcingaObjectTable;

class IcingaServiceSetTable extends IcingaObjectTable
{
    public function getColumns()
    {
        return array(
            'id'          => 'sset.id',
            'name'        => 'sset.object_name',
            'object_type' => 'sset.object_type',
            'description' => 'sset.description',
            'host_name'   => 'h.object_name',
        );
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'name'    => $view->translate('Service set'),
        );
    }

    protected function getActionUrl($row)
    {
        // TODO: Remove once we got a separate apply table
        if ($row->object_type === 'apply') {
            $params['id'] = $row->id;
        } else {
            $params = array('name' => $row->name);
            if ($row->host_name) {
                $params['host'] = $row->host_name;
            }
        }

        return $this->url('director/serviceset', $params);
    }

    protected function getUnfilteredQuery()
    {
        return $this->db()->select()->from(
            array('sset' => 'icinga_service_set'),
            array()
        )->joinLeft(
            array('h' => 'icinga_host'),
            'h.id = sset.host_id',
            array()
        )->where('sset.object_type = ?', 'template')->order('sset.object_name');
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}
