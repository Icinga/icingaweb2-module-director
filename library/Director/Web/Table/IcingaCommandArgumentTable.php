<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\IcingaCommand;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;

class IcingaCommandArgumentTable extends ZfQueryBasedTable
{
    /** @var IcingaCommand */
    protected $command;

    protected $searchColumns = array(
        'ca.argument_name',
        'ca.argument_value',
    );

    public static function create(IcingaCommand $command)
    {
        $self = new static($command->getConnection());
        $self->command = $command;
        return $self;
    }

    public function assemble()
    {
        $this->getAttributes()->set('data-base-target', '_self');
    }

    public function renderRow($row)
    {
        return $this::row([
            Link::create($row->argument_name, 'director/command/arguments', [
                'argument_id' => $row->id,
                'name'        => $this->command->getObjectName()
            ]),
            $row->argument_value
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Argument'),
            $this->translate('Value'),
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['ca' => 'icinga_command_argument'],
            [
                'id'             => 'ca.id',
                'argument_name'  => "COALESCE(ca.argument_name, '(none)')",
                'argument_value' => 'ca.argument_value',
            ]
        )->where(
            'ca.command_id = ?',
            $this->command->get('id')
        )->order('ca.sort_order')->order('ca.argument_name')->limit(100);
    }
}
