<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaCommandTable extends QuickTable
{
    protected $searchColumns = array(
        'command',
    );

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
        return $this->url('director/command', array('name' => $row->command));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'command'      => $view->translate('Command'),
            'command_line' => $view->translate('Command line'),
        );
    }

    protected function getUnfilteredQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('c' => 'icinga_command'),
            array()
        )->joinLeft(
            array('z' => 'icinga_zone'),
            'c.zone_id = z.id',
            array()
        );

        return $query;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where('c.object_type = ?', 'object');
    }
}
