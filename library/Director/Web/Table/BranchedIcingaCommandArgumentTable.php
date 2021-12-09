<?php

namespace Icinga\Module\Director\Web\Table;

use gipfl\IcingaWeb2\Data\SimpleQueryPaginationAdapter;
use gipfl\IcingaWeb2\Table\QueryBasedTable;
use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Director\Db\Branch\Branch;
use Icinga\Module\Director\Objects\IcingaCommand;
use gipfl\IcingaWeb2\Link;

class BranchedIcingaCommandArgumentTable extends QueryBasedTable
{
    /** @var IcingaCommand */
    protected $command;

    /** @var Branch */
    protected $branch;

    protected $searchColumns = [
        'ca.argument_name',
        'ca.argument_value',
    ];

    public function __construct(IcingaCommand $command, Branch $branch)
    {
        $this->command = $command;
        $this->branch = $branch;
        $this->getAttributes()->set('data-base-target', '_self');
    }

    public function renderRow($row)
    {
        return $this::row([
            Link::create($row->argument_name, 'director/command/arguments', [
                'argument' => $row->argument_name,
                'uuid'     => $this->command->getUniqueId()->toString(),
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

    protected function getPaginationAdapter()
    {
        return new SimpleQueryPaginationAdapter($this->getQuery());
    }

    public function getQuery()
    {
        return $this->prepareQuery();
    }

    protected function fetchQueryRows()
    {
        return $this->getQuery()->fetchAll();
    }

    protected function prepareQuery()
    {
        $list = [];
        foreach ($this->command->arguments()->toPlainObject() as $name => $argument) {
            $new = (object) [];
            $new->argument_name = $name;
            $new->argument_value = isset($argument->value) ? $argument->value : null;
            $list[] = $new;
        }

        return (new ArrayDatasource($list))->select();
    }
}
