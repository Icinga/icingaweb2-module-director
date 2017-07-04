<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Web\Table\QuickTable;
use ipl\Html\ValidHtml;

class IcingaCommandArgumentTable extends QuickTable implements ValidHtml
{
    protected $commandObject;

    protected $searchColumns = array(
        'command',
    );

    public function setCommandObject(IcingaCommand $command)
    {
        $this->commandObject = $command;
        if ($this->connection === null) {
            $this->setConnection($command->getConnection());
        }

        return $this;
    }

    public function getColumns()
    {
        return array(
            'id'             => 'ca.id',
            'command_id'     => 'c.id',
            'command'        => 'c.object_name',
            'argument_name'  => "COALESCE(ca.argument_name, '(none)')",
            'argument_value' => 'ca.argument_value',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url(
            'director/command/arguments',
            array(
                'argument_id' => $row->id,
                'name'        => $this->commandObject->object_name
            )
        );
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'argument_name'  => $view->translate('Argument'),
            'argument_value' => $view->translate('Value'),
        );
    }

    public function getBaseQuery()
    {
        return $this->db()->select()->from(
            array('ca' => 'icinga_command_argument'),
            array()
        )->joinLeft(
            array('c' => 'icinga_command'),
            'ca.command_id = c.id',
            array()
        )->order('ca.sort_order')->order('ca.argument_name');
    }
}
