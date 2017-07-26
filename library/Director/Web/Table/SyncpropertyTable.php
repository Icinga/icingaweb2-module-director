<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\SyncRule;
use ipl\Html\Link;
use ipl\Web\Table\ZfQueryBasedTable;

class SyncpropertyTable extends ZfQueryBasedTable
{
    /** @var SyncRule */
    protected $rule;

    public static function create(SyncRule $rule)
    {
        $table = new static($rule->getConnection());
        $table->attributes()->set('data-base-target', '_self');
        $table->rule = $rule;
        return $table;
    }

    public function renderRow($row)
    {
        return $this::tr([
            $this::td($row->source_name),
            $this::td($row->source_expression),
            $this::td(new Link(
                $row->destination_field,
                'director/syncrule/editproperty',
                [
                    'id'      => $row->id,
                    'rule_id' => $row->rule_id,
                ]
            )),
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Source name'),
            $this->translate('Source field'),
            $this->translate('Destination')
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['r' => 'sync_rule'],
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
            ['p' => 'sync_property'],
            'r.id = p.rule_id',
            []
        )->join(
            ['s' => 'import_source'],
            's.id = p.source_id',
            []
        )->where(
            'r.id = ?',
            $this->rule->get('id')
        )->order('p.priority');
    }
}
