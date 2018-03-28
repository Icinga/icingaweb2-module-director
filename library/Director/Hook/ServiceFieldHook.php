<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Field\FieldSpec;
use Icinga\Module\Director\Objects\IcingaService;

abstract class ServiceFieldHook
{
    public function wants(IcingaService $service)
    {
        return true;
    }

    /**
     * @return FieldSpec
     */
    abstract public function getFieldSpec(IcingaService $service);
}
