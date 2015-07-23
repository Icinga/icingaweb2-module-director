<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class SyncruleTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'                => 's.id',
            'rule_name'         => 's.rule_name',
            'object_type'       => 's.object_type',
            'update_policy'     => 's.update_policy',
            'purge_existing'    => 's.purge_existing',
            'filter_expression' => 's.filter_expression',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/syncrule/add', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'rule_name' => $view->translate('Rule name'),
            'object_type'  => $view->translate('Object type'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
             array('s' => 'sync_rule'),
	     array()
        )->order('id');

        return $query;
    }
}
