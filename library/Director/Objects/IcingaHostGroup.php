<?php

namespace Icinga\Module\Director\Objects;

class IcingaHostGroup extends IcingaObjectGroup
{
    protected $table = 'icinga_hostgroup';

    // TODO: move to IcingaObjectGroup when supported for ServiceGroup
    protected $supportsApplyRules = true;
}
