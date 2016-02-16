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

    protected function renderEnable_notifications()
    {
        return $this->renderBooleanProperty('enable_notifications');
    }
}
