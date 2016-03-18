<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\IcingaObjectTable;

class IcingaCommandTable extends IcingaObjectTable
{
    protected $searchColumns = array(
        'command',
    );

    public function getColumns()
    {
        return array(
            'id'           => 'c.id',
            'command'      => 'c.object_name',
            'object_type'  => 'c.object_type',
            'command_line' => 'c.command',
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
        )->order('c.object_name');

        return $query;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}
