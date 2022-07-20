<?php

namespace Icinga\Module\Director\Resolver;

use Icinga\Module\Director\Objects\IcingaHost;
use InvalidArgumentException;

class OverrideHelper
{
    public static function applyOverriddenVars(IcingaHost $host, $serviceName, $properties)
    {
        static::assertVarsForOverrides($properties);
        $current = $host->getOverriddenServiceVars($serviceName);
        foreach ($properties as $key => $value) {
            if ($key === 'vars') {
                foreach ($value as $k => $v) {
                    $current->$k = $v;
                }
            } else {
                $current->{substr($key, 5)} = $value;
            }
        }
        $host->overrideServiceVars($serviceName, $current);
    }

    public static function assertVarsForOverrides($properties)
    {
        if (empty($properties)) {
            return;
        }

        foreach ($properties as $key => $value) {
            if ($key !== 'vars' && substr($key, 0, 5) !== 'vars.') {
                throw new InvalidArgumentException("Only Custom Variables can be set based on Variable Overrides");
            }
        }
    }
}
