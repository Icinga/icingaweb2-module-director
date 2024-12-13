<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class IcingaScheduledDowntimeRange extends DbObject
{
    protected $keyName = ['scheduled_downtime_id', 'range_key', 'range_type'];

    protected $table = 'icinga_scheduled_downtime_range';

    protected $defaultProperties = [
        'scheduled_downtime_id' => null,
        'range_key'             => null,
        'range_value'           => null,
        'range_type'            => 'include',
        'merge_behaviour'       => 'set',
    ];

    public function isActive($now = null)
    {
        if ($now === null) {
            $now = time();
        }

        if (false === ($weekDay = $this->getWeekDay($this->get('range_key')))) {
            // TODO, dates are not yet supported
            return false;
        }

        if ((int) date('w', $now) !== $weekDay) {
            return false;
        }

        $timeRanges = preg_split('/\s*,\s*/', $this->get('range_value'), -1, PREG_SPLIT_NO_EMPTY);
        foreach ($timeRanges as $timeRange) {
            if ($this->timeRangeIsActive($timeRange, $now)) {
                return true;
            }
        }

        return false;
    }

    protected function timeRangeIsActive($rangeString, $now)
    {
        $hBegin = $mBegin = $hEnd = $mEnd = null;
        if (sscanf($rangeString, '%2d:%2d-%2d:%2d', $hBegin, $mBegin, $hEnd, $mEnd) === 4) {
            if (
                $this->timeFromHourMin($hBegin, $mBegin, $now) <= $now
                && $this->timeFromHourMin($hEnd, $mEnd, $now) >= $now
            ) {
                return true;
            }
        } else {
            // TODO: throw exception?
        }

        return false;
    }

    protected function timeFromHourMin($hour, $min, $now)
    {
        return strtotime(sprintf('%s %02d:%02d:00', date('Y-m-d', $now), $hour, $min));
    }

    protected function getWeekDay($day)
    {
        switch ($day) {
            case 'sunday':
                return 0;
            case 'monday':
                return 1;
            case 'tuesday':
                return 2;
            case 'wednesday':
                return 3;
            case 'thursday':
                return 4;
            case 'friday':
                return 5;
            case 'saturday':
                return 6;
        }

        return false;
    }
}
