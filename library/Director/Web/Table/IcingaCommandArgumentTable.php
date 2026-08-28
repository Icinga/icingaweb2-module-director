<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Director\Objects\IcingaCommand;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;

class IcingaCommandArgumentTable extends ZfQueryBasedTable
{
    /** @var IcingaCommand */
    protected $command;

    protected $searchColumns = [
        'ca.argument_name',
        'ca.argument_value',
    ];

    public function __construct(IcingaCommand $command)
    {
        $this->command = $command;
        parent::__construct($command->getConnection());
        $this->getAttributes()->set('data-base-target', '_self');
    }

    public function renderRow($row)
    {
        return $this::row([
            Link::create($row->argument_name, 'director/command/arguments', [
                'argument' => $row->argument_name,
                'name'     => $this->command->getObjectName()
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
        $id = $this->command->get('id');
        if ($id === null) {
            return new ArrayDatasource([]);
        }
        return $this->db()->select()->from(
            ['ca' => 'icinga_command_argument'],
            [
                'id'             => 'ca.id',
                'argument_name'  => "COALESCE(ca.argument_name, '(none)')",
                'argument_value' => 'ca.argument_value',
            ]
        )->where(
            'ca.command_id = ?',
            $id
        )->order('ca.sort_order')->order('ca.argument_name')->limit(100);
    }
}
