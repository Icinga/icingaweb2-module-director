<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Module\Director\Import\Sync;
use Icinga\Module\Director\Objects\SyncRule;
use Exception;

class SyncruleTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'                => 's.id',
            'rule_name'         => 's.rule_name',
            'sync_state'        => 's.sync_state',
            'object_type'       => 's.object_type',
            'update_policy'     => 's.update_policy',
            'purge_existing'    => 's.purge_existing',
            'filter_expression' => 's.filter_expression',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/syncrule', array('id' => $row->id));
    }

    protected function listTableClasses()
    {
        return array_merge(array('syncstate'), parent::listTableClasses());
    }

    protected function renderAdditionalActions($row)
    {
        return $this->view->qlink(
            'Run',
            'director/syncrule/run',
            array('id' => $row->id),
            array('data-base-target' => '_main')
        );
    }

    protected function getRowClasses($row)
    {
        return $row->sync_state;
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
        )->order('rule_name');

        return $query;
    }
}
