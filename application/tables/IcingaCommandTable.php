<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaCommandTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'           => 'c.id',
            'command'      => 'c.object_name',
            'command_line' => 'c.command',
            'zone'         => 'z.object_name',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/command', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'command'      => $view->translate('Command'),
            'command_line' => $view->translate('Command line'),
            'zone'         => $view->translate('Zone'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('c' => 'icinga_command'),
            $this->getColumns()
        )->joinLeft(
            array('z' => 'icinga_zone'),
            'c.zone_id = z.id',
            array()
        );

        return $db->fetchAll($query);
    }
}
