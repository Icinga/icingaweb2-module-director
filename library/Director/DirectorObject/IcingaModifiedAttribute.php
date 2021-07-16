<?php

namespace Icinga\Module\Director\DirectorObject;

use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Daemon\DaemonUtil;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Objects\IcingaObject;

class IcingaModifiedAttribute extends DbObject
{

    protected $table = 'icinga_modified_attribute';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = [
        'id' => null,
        'activity_id' => null,
        'action' => null,
        'modification' => null,
        'ts_scheduled' => null,
        'ts_applied' => null,
        'icinga_object_type' => null,
        'icinga_object_name' => null,
        'state' => null
    ];

    public function getUniqueKey()
    {
        return $this->get('icinga_object_type') . '!' . $this->get('icinga_object_name');
    }

    public function getModifiedAttributes()
    {
        $modifications = $this->get('modification');
        if (is_null($modifications)) {
            return [];
        }
        return Json::decode($modifications);
    }

    public static function prepareIcingaModifiedAttributeForSingleObject(IcingaObject $object, $activityId = null)
    {
        // TODO: throw if not supported
        $action = self::getActionFromObject($object);
        $resolvedProperties = self::getObjectModifiedPropertyFromAction($object, $action);
        return IcingaModifiedAttribute::create([
            'activity_id' => $activityId,
            'action' => $action,
            'modification' => $resolvedProperties,
            'ts_scheduled' => DaemonUtil::timestampWithMilliseconds(),
            'icinga_object_type' => $object->getIcingaObjectType(),
            'icinga_object_name' => $object->getIcingaObjectName(),
            'state' => 'scheduled'
        ]);
    }

    protected static function getActionFromObject(IcingaObject $object)
    {
        if ($object->shouldBeRemoved()) {
            $action = 'delete';
        } elseif ($object->hasBeenLoadedFromDb()) {
            $action = 'modify';
        } else {
            $action = 'create';
        }

        return $action;
    }

    protected static function getObjectModifiedPropertyFromAction(IcingaObject $object, $action)
    {
        $properties = new \stdClass();
        if ($action === 'modify') {
            $properties = $object->getModifiedProperties();
        } elseif ($action === 'create') {
            $properties = $object->toApiObject(true, true);
        }
        return Json::encode($properties);
    }
}
