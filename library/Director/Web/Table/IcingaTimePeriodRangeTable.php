<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\IcingaTimePeriod;
use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;

class IcingaTimePeriodRangeTable extends ZfQueryBasedTable
{
    protected $period;

    protected $searchColumns = array(
        'range_key',
        'range_value',
    );

    public static function load(IcingaTimePeriod $period)
    {
        $table = new static($period->getConnection());
        $table->period = $period;
        $table->getAttributes()->set('data-base-target', '_self');
        return $table;
    }

    public function renderRow($row)
    {
        return $this::row([
            Link::create(
                $row->range_key,
                'director/timeperiod/ranges',
                array(
                    'name'       => $this->period->object_name,
                    'range'      => $row->range_key,
                    'range_type' => 'include'
                )
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
            ['r' => 'icinga_timeperiod_range'],
            [
                'timeperiod_id' => 'r.timeperiod_id',
                'range_key'     => 'r.range_key',
                'range_value'   => 'r.range_value',
            ]
        )->where('r.timeperiod_id = ?', $this->period->id);
    }
}
