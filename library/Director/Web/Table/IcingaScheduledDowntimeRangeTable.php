<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\IcingaScheduledDowntime;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;

class IcingaScheduledDowntimeRangeTable extends ZfQueryBasedTable
{
    /** @var IcingaScheduledDowntime */
    protected $downtime;

    protected $searchColumns = [
        'range_key',
        'range_value',
    ];

    /**
     * @param IcingaScheduledDowntime $downtime
     * @return static
     */
    public static function load(IcingaScheduledDowntime $downtime)
    {
        $table = new static($downtime->getConnection());
        $table->downtime = $downtime;
        $table->getAttributes()->set('data-base-target', '_self');

        return $table;
    }

    public function renderRow($row)
    {
        return $this::row([
            Link::create(
                $row->range_key,
                'director/scheduled-downtime/ranges',
                [
                    'name'       => $this->downtime->getObjectName(),
                    'range'      => $row->range_key,
                    'range_type' => 'include'
                ]
            ),
            $row->range_value
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Day(s)'),
            $this->translate('Timeperiods'),
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['r' => 'icinga_scheduled_downtime_range'],
            [
                'scheduled_downtime_id' => 'r.scheduled_downtime_id',
                'range_key'   => 'r.range_key',
                'range_value' => 'r.range_value',
            ]
        )->where('r.scheduled_downtime_id = ?', $this->downtime->id);
    }
}
