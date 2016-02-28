<?php

namespace Icinga\Module\Director\Objects;

class IcingaUser extends IcingaObject
{
    protected $table = 'icinga_user';

    protected $defaultProperties = array(
        'id'                    => null,
        'object_name'           => null,
        'object_type'           => null,
        'disabled'              => 'n',
        'display_name'          => null,
        'email'                 => null,
        'pager'                 => null,
        'enable_notifications'  => null,
        'period_id'             => null,
        'zone_id'               => null,
    );

    protected $supportsGroups = true;

    protected $supportsCustomVars = true;

    protected $supportsImports = true;

    protected $booleans = array(
        'enable_notifications' => 'enable_notifications'
    );

    protected $states = array();

    protected function setStates($value)
    {
        $states = $value;
        sort($states);
        if ($this->states !== $states) {
            $this->states = $states;
            $this->hasBeenModified = true;
        }
    }
}
