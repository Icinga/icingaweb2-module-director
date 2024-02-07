<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\SyncRule;
use gipfl\IcingaWeb2\Link;

class SyncRunTable extends IntlZfQueryBasedTable
{
    /** @var SyncRule */
    protected $rule;

    public static function create(SyncRule $rule)
    {
        $table = new static($rule->getConnection());
        $table->getAttributes()
            ->set('data-base-target', '_self')
            ->add('class', 'history');
        $table->rule = $rule;
        return $table;
    }

    public function renderRow($row)
    {
        $time = strtotime($row->start_time);
        $this->renderDayIfNew($time);
        return $this::tr([
            $this::td($this->makeSummary($row)),
            $this::td(new Link(
                $this->getTime($time),
                'director/syncrule/history',
                [
                    'id'     => $row->rule_id,
                    'run_id' => $row->id,
                ]
            ))
        ]);
    }

    protected function makeSummary($row)
    {
        $parts = [];
        if ($row->objects_created > 0) {
            $parts[] = sprintf(
                $this->translate('%d created'),
                $row->objects_created
            );
        }
        if ($row->objects_modified > 0) {
            $parts[] = sprintf(
                $this->translate('%d modified'),
                $row->objects_modified
            );
        }
        if ($row->objects_deleted > 0) {
            $parts[] = sprintf(
                $this->translate('%d deleted'),
                $row->objects_deleted
            );
        }

        return implode(', ', $parts);
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            array('sr' => 'sync_run'),
            [
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
            ]
        )->where(
            'sr.rule_id = ?',
            $this->rule->get('id')
        )->order('start_time DESC');
    }
}
