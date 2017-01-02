<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaHostServiceSetTable extends QuickTable
{
    protected $title;

    protected $host;

    private $lastSetId;

    public function getColumns()
    {
        return array(
            'id'               => 'pset.id',
            'service_set_id'   => 'sset.id',
            'service_set_name' => 'sset.object_name',
            'name'             => 's.object_name',
            'description'      => 'sset.description',
            'host_name'        => 'h.object_name',
        );
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'name' => $view->translate('Service'),
        );
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    protected function beginTableBody()
    {
        return '';
    }

    protected function renderRow($row)
    {
        $html = '';
        $view = $this->view();
        if ($row->service_set_id !== $this->lastSetId) {
            if ($this->lastSetId === null) {
                $html .= "</tbody>\n";
            }
            $html .= parent::renderTitles((object) array(
                'name' => sprintf($view->translate('Service set: %s'), $row->service_set_name)
            ));
            $html .= "<tbody>\n";
            $this->lastSetId = $row->service_set_id;
        }

        return $html . parent::renderRow($row);
    }

    protected function renderTitles($row)
    {
        return '';
    }

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
    }

    protected function getActionUrl($row)
    {
        $params = array(
            'name'         => $row->host_name,
            'service'      => $row->name,
            'serviceSet' => $row->id,
        );

        return $this->url('director/host/servicesetservice', $params);
    }

    protected function getUnfilteredQuery()
    {
        return $this->db()->select()->from(
            array('pset' => 'icinga_service_set'),
            array()
        )->join(
            array('sseti' => 'icinga_service_set_inheritance'),
            'pset.id = sseti.parent_service_set_id',
            array()
        )->join(
            array('sset' => 'icinga_service_set'),
            'sset.id = sseti.service_set_id',
            array()
        )->join(
            array('h' => 'icinga_host'),
            'h.id = sset.host_id',
            array()
        )->join(
            array('s' => 'icinga_service'),
            'pset.id = s.service_set_id',
            array()
        )->where('sset.host_id = ?', $this->host->id)->order('sset.object_name');
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}
