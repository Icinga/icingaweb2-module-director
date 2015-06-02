<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaCommandArgumentTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'             => 'ca.id',
            'command'        => 'c.object_name',
            'argument_name'  => 'ca.argument_name',
            'argument_value' => 'ca.argument_value',
            // 'required'       => 'ca.required',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/object/commandargument', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'command'        => $view->translate('Command'),
            'argument_name'  => $view->translate('Argument'),
            'argument_value' => $view->translate('Value'),
        );
    }

    public function fetchData()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('ca' => 'icinga_command_argument'),
            $this->getColumns()
        )->joinLeft(
            array('c' => 'icinga_command'),
            'ca.command_id = c.id',
            array()
        );

        return $db->fetchAll($query);
    }
}
