<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;

class IcingaUser extends IcingaObject implements ExportInterface
{
    protected $table = 'icinga_user';

    protected $defaultProperties = array(
        'id'                    => null,
        'uuid'                  => null,
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

    protected $uuidColumn = 'uuid';

    protected $supportsGroups = true;

    protected $supportsCustomVars = true;

    protected $supportsFields = true;

    protected $supportsCustomProperties = true;

    protected $supportsImports = true;

    protected $booleans = array(
        'enable_notifications' => 'enable_notifications'
    );

    protected $relatedSets = array(
        'states' => 'StateFilterSet',
        'types'  => 'TypeFilterSet',
    );

    protected $relations = array(
        'period' => 'IcingaTimePeriod',
        'zone'   => 'IcingaZone',
    );

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }
}
