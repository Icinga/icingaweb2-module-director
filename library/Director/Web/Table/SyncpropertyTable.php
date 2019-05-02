<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\SyncRule;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\Extension\ZfSortablePriority;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;

class SyncpropertyTable extends ZfQueryBasedTable
{
    use ZfSortablePriority;

    /** @var SyncRule */
    protected $rule;

    protected $searchColumns = [
        'source_expression',
        'destination_field',
    ];

    protected $keyColumn = 'id';

    protected $priorityColumn = 'priority';

    public static function create(SyncRule $rule)
    {
        $table = new static($rule->getConnection());
        $table->getAttributes()->set('data-base-target', '_self');
        $table->rule = $rule;
        return $table;
    }

    public function render()
    {
        return $this->renderWithSortableForm();
    }

    public function renderRow($row)
    {
        return $this->addSortPriorityButtons(
            $this::row([
                $row->source_name,
                $row->source_expression,
                new Link(
                    $row->destination_field,
                    'director/syncrule/editproperty',
                    [
                        'id'      => $row->id,
                        'rule_id' => $row->rule_id,
                    ]
                ),
            ]),
            $row
        );
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Source name'),
            $this->translate('Source field'),
            $this->translate('Destination'),
            $this->getSortPriorityTitle()
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['p' => 'sync_property'],
            [
                'id'                => 'p.id',
                'rule_id'           => 'p.rule_id',
                'rule_name'         => 'r.rule_name',
                'source_id'         => 'p.source_id',
                'source_name'       => 's.source_name',
                'source_expression' => 'p.source_expression',
                'destination_field' => 'p.destination_field',
                'priority'          => 'p.priority',
                'filter_expression' => 'p.filter_expression',
                'merge_policy'      => 'p.merge_policy'
            ]
        )->join(
            ['r' => 'sync_rule'],
            'r.id = p.rule_id',
            []
        )->join(
            ['s' => 'import_source'],
            's.id = p.source_id',
            []
        )->where(
            'p.rule_id = ?',
            $this->rule->get('id')
        )->order('p.priority');
    }
}
