<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaHostServiceTable extends QuickTable
{
    protected $title;

    protected $host;

    protected $inheritedBy;

    protected $searchColumns = array(
        'service',
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

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
    }

    public function setInheritedBy(IcingaHost $host)
    {
        $this->inheritedBy = $host;
        return $this;
    }

    protected function getActionUrl($row)
    {
        if ($target = $this->inheritedBy) {
            $params = array(
                'name'          => $target->object_name,
                'service'       => $row->service,
                'inheritedFrom' => $row->host,
            );

            return $this->url('director/host/inheritedservice', $params);
        }

        if ($row->object_type === 'apply') {
            $params['id'] = $row->id;
        } else {
            $params = array('name' => $row->service);
            if ($row->host !== null) {
                $params['host'] = $row->host;
            }
        }

        return $this->url('director/service/edit', $params);
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'service' => $this->title ?: $view->translate('Servicename'),
        );
    }

    public function getUnfilteredQuery()
    {
        return $this->db()->select()->from(
            array('s' => 'icinga_service'),
            array()
        )->joinLeft(
            array('h' => 'icinga_host'),
            'h.id = s.host_id',
            array()
        )->order('s.object_name');
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where(
            's.host_id = ?',
            $this->host->id
        );
    }
}
