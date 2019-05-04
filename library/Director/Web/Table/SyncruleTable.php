<?php

namespace Icinga\Module\Director\Web\Table;

use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;

class SyncruleTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'rule_name',
        'description',
    ];

    protected function assemble()
    {
        $this->getAttributes()->add('class', 'syncstate');
        parent::assemble();
    }

    public function renderRow($row)
    {
        $caption = [Link::create(
            $row->rule_name,
            'director/syncrule',
            ['id' => $row->id]
        )];
        if ($row->description !== null) {
            $caption[] = ': ' . $row->description;
        }

        if ($row->sync_state === 'failing' && $row->last_error_message) {
            $caption[] = ' (' . $row->last_error_message . ')';
        }

        $tr = $this::row([$caption, $row->object_type]);
        $tr->getAttributes()->add('class', $row->sync_state);

        return $tr;
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Rule name'),
            $this->translate('Object type'),
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['s' => 'sync_rule'],
            [
                'id'                 => 's.id',
                'rule_name'          => 's.rule_name',
                'sync_state'         => 's.sync_state',
                'object_type'        => 's.object_type',
                'update_policy'      => 's.update_policy',
                'purge_existing'     => 's.purge_existing',
                'filter_expression'  => 's.filter_expression',
                'last_error_message' => 's.last_error_message',
                'description'        => 's.description',
            ]
        )->order('rule_name');
    }
}
