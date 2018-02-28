<?php

namespace Icinga\Module\Director\Objects\Extension;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

trait FlappingSupport
{
    /**
     * @param $value
     * @return string
     * @codingStandardsIgnoreStart
     */
    protected function renderFlapping_threshold_high($value)
    {
        return $this->renderFlappingThreshold('flapping_threshold_high', $value);
    }

    /**
     * @param $value
     * @return string
     */
    protected function renderFlapping_threshold_low($value)
    {
        // @codingStandardsIgnoreEnd
        return $this->renderFlappingThreshold('flapping_threshold_low', $value);
    }

    protected function renderFlappingThreshold($key, $value)
    {
        return sprintf(
            "    try { // This setting is only available in Icinga >= 2.8.0\n"
            . "    %s"
            . "    } except { globals.directorWarnOnceForThresholds() }\n",
            c::renderKeyValue($key, c::renderFloat($value))
        );
    }
}
