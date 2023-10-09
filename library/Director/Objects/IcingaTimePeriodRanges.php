<?php

namespace Icinga\Module\Director\Objects;

use Countable;
use Iterator;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;

class IcingaTimePeriodRanges extends IcingaRanges implements Iterator, Countable, IcingaConfigRenderer
{
    protected $rangeClass = IcingaTimePeriodRange::class;
    protected $objectIdColumn = 'timeperiod_id';

    public function toLegacyConfigString()
    {
        if (empty($this->ranges) && $this->object->isTemplate()) {
            return '';
        }

        $out = '';

        foreach ($this->ranges as $range) {
            $out .= c1::renderKeyValue(
                $range->get('range_key'),
                $range->get('range_value')
            );
        }
        if ($out !== '') {
            $out = "\n".$out;
        }

        return $out;
    }
}
