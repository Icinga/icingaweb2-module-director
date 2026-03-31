<?php

namespace Icinga\Module\Director\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Icinga\Module\Director\Daemon\DaemonUtil;

class JobTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'job_name',
    ];

    protected function assemble()
    {
        $this->getAttributes()->add('class', 'jobs');
        parent::assemble();
    }

    public function renderRow($row)
    {
        $caption = [Link::create(
            $row->job_name,
            'director/job',
            ['id' => $row->id]
        )];

        if ($row->last_attempt_succeeded === 'n' && $row->last_error_message) {
            $caption[] = ' (' . $row->last_error_message . ')';
        }

        $tr = $this::row([$caption]);
        $tr->getAttributes()->add('class', $this->getJobClasses($row));

        return $tr;
    }

    protected function getJobClasses($row)
    {
        if ($row->ts_last_attempt === null) {
            return 'pending';
        }

        if ($row->ts_last_attempt + $row->run_interval * 1000 < DaemonUtil::timestampWithMilliseconds()) {
            return 'pending';
        }

        if ($row->last_attempt_succeeded === 'y') {
            return 'ok';
        } elseif ($row->last_attempt_succeeded === 'n') {
            return 'critical';
        } else {
            return 'unknown';
        }
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Job name'),
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['j' => 'director_job'],
            [
                'id'                     => 'j.id',
                'job_name'               => 'j.job_name',
                'job_class'              => 'j.job_class',
                'disabled'               => 'j.disabled',
                'run_interval'           => 'j.run_interval',
                'last_attempt_succeeded' => 'j.last_attempt_succeeded',
                'ts_last_attempt'        => 'j.ts_last_attempt',
                'ts_last_error'          => 'j.ts_last_error',
                'last_error_message'     => 'j.last_error_message',
            ]
        )->order('job_name');
    }
}
