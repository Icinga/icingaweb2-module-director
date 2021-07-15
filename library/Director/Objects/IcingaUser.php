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

    protected $supportsFields = true;

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

    public function export()
    {
        return ImportExportHelper::simpleExport($this);
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return IcingaUser
     * @throws DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        $key = $properties['object_name'];

        if ($replace && static::exists($key, $db)) {
            $object = static::load($key, $db);
        } elseif (static::exists($key, $db)) {
            throw new DuplicateKeyException(
                'Cannot import, %s "%s" already exists',
                static::create([])->getShortTableName(),
                $key
            );
        } else {
            $object = static::create([], $db);
        }

        // $object->newFields = $properties['fields'];
        unset($properties['fields']);
        $object->setProperties($properties);

        return $object;
    }

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }
}
