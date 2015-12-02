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
            'object_type'  => 'c.object_type',
            'command_line' => 'c.command',
        );
    }

    protected function getActionUrl($row)
    {
        if ($row->object_type === 'external_object') {
            return $this->url('director/command/render', array('name' => $row->command));
        } else {
            return $this->url('director/command', array('name' => $row->command));
        }
    }

    protected function listTableClasses()
    {
        return array_merge(array('check-commands'), parent::listTableClasses());
    }

    protected function getRowClasses($row)
    {
        switch ($row->object_type) {
            case 'object':
                return 'icinga-object';
            case 'template':
                return 'icinga-template';
            case 'external_object':
                return 'icinga-object-external';
        }
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
