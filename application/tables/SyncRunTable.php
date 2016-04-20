<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;
use Exception;

class SyncRunTable extends QuickTable
{
    protected $revalidate = false;

    public function getColumns()
    {
        return array(
            'id'                    => 'sr.id',
            'rule_id'               => 'sr.rule_id',
            'rule_name'             => 'sr.rule_name',
            'start_time'            => 'sr.start_time',
            'duration_ms'           => 'sr.duration_ms',
            'objects_deleted'       => 'sr.objects_deleted',
            'objects_created'       => 'sr.objects_created',
            'objects_modified'      => 'sr.objects_modified',
            'last_former_activity'  => 'sr.last_former_activity',
            'last_related_activity' => 'sr.last_related_activity',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url(
            'director/syncrule/history',
            array(
                'id'     => $row->rule_id,
                'run_id' => $row->id,
            )
        );
    }

    public function getTitles()
    {
        $singleRule = false;

        foreach ($this->enforcedFilters as $filter) {
            if (in_array('rule_id', $filter->listFilteredColumns())) {
                $singleRule = true;
                break;
            }
        }

        $view = $this->view();

        if ($singleRule) {
            return array(
                'start_time'       => $view->translate('Start time'),
                'objects_created'  => $view->translate('Created'),
                'objects_modified' => $view->translate('Modified'),
                'objects_deleted'  => $view->translate('Deleted'),
            );
        } else {
            return array(
                'rule_name'  => $view->translate('Rule name'),
                'start_time' => $view->translate('Start time'),
            );
        }
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('sr' => 'sync_run'),
            array()
        )->order('start_time DESC');

        return $query;
    }
}
