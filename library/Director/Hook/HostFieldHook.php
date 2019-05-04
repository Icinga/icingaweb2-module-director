<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Field\FieldSpec;
use Icinga\Module\Director\Objects\IcingaHost;

abstract class HostFieldHook
{
    public function wants(IcingaHost $host)
    {
        return true;
    }

    /**
     * @return FieldSpec
     */
    abstract public function getFieldSpec(IcingaHost $host);
}
