<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class SyncpropertyTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'                => 's.id',
            'rule_id'           => 's.rule_id',
            'source_id'         => 's.source_id',
            'source_expression' => 's.source_expression',
            'destination_field' => 's.destination_field',
            'priority'          => 's.priority',
            'filter_expression' => 's.filter_expression',
            'merge_policy'	=> 's.merge_policy'
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/syncproperty/add', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
	    'source_id' => $view->translate('Source id'),
            'source_expression' => $view->translate('Source field'),
            'destination_field'  => $view->translate('Destination')
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
             array('s' => 'sync_property'),
	     array()
        )->order('id');

        return $query;
    }
}
