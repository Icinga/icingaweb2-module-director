<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaServiceSetHostTable extends QuickTable
{
    protected $set;

    protected $searchColumns = array(
        'host',
    );

    public function getColumns()
    {
        return array(
            'id'          => 'h.id',
            'host'        => 'h.object_name',
            'object_type' => 'h.object_type',
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
            'name' => $row->host
        );

        return $this->url('director/host', $params);
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'host' => $view->translate('Hostname'),
        );
    }

    public function getUnfilteredQuery()
    {
        return $this->db()->select()->from(
            array('h' => 'icinga_host'),
            array()
        )->joinLeft(
            array('ssh' => 'icinga_service_set'),
            'ssh.host_id = h.id',
            array()
        )->joinLeft(
            array('ssih' => 'icinga_service_set_inheritance'),
            'ssih.service_set_id = ssh.id',
            array()
        )->order('h.object_name');
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where(
            'ssih.parent_service_set_id = ?',
            $this->set->id
        );
    }
}
