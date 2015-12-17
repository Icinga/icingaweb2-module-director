<?php

namespace Icinga\Module\Director\Objects;

class IcingaEndpoint extends IcingaObject
{
    protected $table = 'icinga_endpoint';

    protected $supportsImports = true;

    protected $defaultProperties = array(
        'id'                    => null,
        'zone_id'               => null,
        'object_name'           => null,
        'host'                  => null,
        'port'                  => null,
        'log_duration'          => null,
        'object_type'           => null,
        'apiuser_id'            => null,
    );

    protected $relations = array(
        'zone'    => 'IcingaZone',
        'apiuser' => 'IcingaApiUser',
    );

    protected function renderLog_duration()
    {
        return $this->renderPropertyAsSeconds('log_duration');
    }

    protected function renderApiuser_id()
    {
        return '';
    }
}
