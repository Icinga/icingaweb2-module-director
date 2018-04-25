<?php

namespace Icinga\Module\Director\Objects\Extension;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;

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

    protected function renderLegacyEnable_flapping($value)
    {
        return c1::renderKeyValue('flap_detection_enabled', c1::renderBoolean($value));
    }

    protected function renderLegacyFlapping_threshold_high($value)
    {
        return c1::renderKeyValue('high_flap_threshold', $value);
    }

    protected function renderLegacyFlapping_threshold_low($value)
    {
        // @codingStandardsIgnoreEnd
        return c1::renderKeyValue('low_flap_threshold', $value);
    }
}
