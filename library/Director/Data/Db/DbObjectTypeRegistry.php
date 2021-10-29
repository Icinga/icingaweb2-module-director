<?php

namespace Icinga\Module\Director\Data\Db;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;

class DbObjectTypeRegistry
{
    /**
     * @param $type
     * @return string|DbObject Fake typehint for IDE
     */
    public static function classByType($type)
    {
        // allow for icinga_host and host
        $type = lcfirst(preg_replace('/^icinga_/', '', $type));

        // Hint: Sync/Import are not IcingaObjects, this should be reconsidered:
        if (strpos($type, 'import') === 0 || strpos($type, 'sync') === 0) {
            $prefix = '';
        } elseif (strpos($type, 'data') === false) {
            $prefix = 'Icinga';
        } else {
            $prefix = 'Director';
        }

        // TODO: Provide a more sophisticated solution
        if ($type === 'hostgroup') {
            $type = 'hostGroup';
        } elseif ($type === 'usergroup') {
            $type = 'userGroup';
        } elseif ($type === 'timeperiod') {
            $type = 'timePeriod';
        } elseif ($type === 'servicegroup') {
            $type = 'serviceGroup';
        } elseif ($type === 'service_set' || $type === 'serviceset') {
            $type = 'serviceSet';
        } elseif ($type === 'apiuser') {
            $type = 'apiUser';
        } elseif ($type === 'host_template_choice') {
            $type = 'templateChoiceHost';
        } elseif ($type === 'service_template_choice') {
            $type = 'TemplateChoiceService';
        } elseif ($type === 'scheduled_downtime' || $type === 'scheduled-downtime') {
            $type = 'ScheduledDowntime';
        }

        return 'Icinga\\Module\\Director\\Objects\\' . $prefix . ucfirst($type);
    }

    public static function tableNameByType($type)
    {
        $class = static::classByType($type);
        $dummy = $class::create([]);

        return $dummy->getTableName();
    }

    public static function shortTypeForObject(DbObject $object)
    {
        if ($object instanceof IcingaObject) {
            return $object->getShortTableName();
        }

        return $object->getTableName();
    }

    public static function newObject($type, $properties = [], Db $db = null)
    {
        /** @var DbObject $class fake hint for the IDE, it's a string */
        $class = self::classByType($type);
        return $class::create($properties, $db);
    }
}
