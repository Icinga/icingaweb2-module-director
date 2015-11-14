<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class SyncpropertyTable extends QuickTable
{
    public function getColumns()
    {
        return array(
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
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url(
            'director/syncrule/editproperty',
            array(
                'id'      => $row->id,
                'rule_id' => $row->rule_id,
            )
        );
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            //'rule_name'         => $view->translate('Rule name'),
            'source_name'       => $view->translate('Source name'),
            'source_expression' => $view->translate('Source field'),
            'destination_field' => $view->translate('Destination')
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('r' => 'sync_rule'),
            array()
        )->join(
            array('p' => 'sync_property'),
            'r.id = p.rule_id',
            array()
        )->join(
            array('s' => 'import_source'),
            's.id = p.source_id',
            array()
        )->order('id');

        return $query;
    }
}
