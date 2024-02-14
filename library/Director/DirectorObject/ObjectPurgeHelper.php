<?php

namespace Icinga\Module\Director\DirectorObject;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use InvalidArgumentException;

class ObjectPurgeHelper
{
    protected $db;

    protected $force = false;

    public function __construct(Db $db)
    {
        $this->db = $db;
    }

    public function force($force = true)
    {
        $this->force = $force;
        return $this;
    }

    public function purge(array $keep, $class, $objectType = null)
    {
        if (empty($keep) && ! $this->force) {
            throw new InvalidArgumentException('I will NOT purge all object unless being forced to do so');
        }
        $db = $this->db->getDbAdapter();
        /** @var IcingaObject $class cheating, it's a class name, not an object */
        $dummy = $class::create();
        assert($dummy instanceof IcingaObject);
        $keyCols = (array) $dummy->getKeyName();
        if ($objectType !== null) {
            $keyCols[] = 'object_type';
        }

        $keepKeys = [];
        foreach ($keep as $object) {
            if ($object instanceof \stdClass) {
                $properties = (array) $object;
                // TODO: this is object-specific and to be found in the ::import() function!
                unset($properties['fields']);
                $object = $class::fromPlainObject($properties);
            } elseif (get_class($object) !== $class) {
                throw new InvalidArgumentException(
                    'Can keep only matching objects, expected "%s", got "%s',
                    $class,
                    get_class($object)
                );
            }
            $key = [];
            foreach ($keyCols as $col) {
                $key[$col] = $object->get($col);
            }
            $keepKeys[$this->makeRowKey($key)] = true;
        }

        $query = $db->select()->from(['o' => $dummy->getTableName()], $keyCols);
        if ($objectType !== null) {
            $query->where('object_type = ?', $objectType);
        }
        $allExisting = [];
        foreach ($db->fetchAll($query) as $row) {
            $allExisting[$this->makeRowKey($row)] = $row;
        }
        $remove = [];
        foreach ($allExisting as $key => $keyProperties) {
            if (! isset($keepKeys[$key])) {
                $remove[] = $keyProperties;
            }
        }
        $db->beginTransaction();
        foreach ($remove as $keyProperties) {
            $keyColumn = $class::getKeyColumnName();
            if (is_array($keyColumn)) {
                $object = $class::load((array) $keyProperties, $this->db);
            } else {
                $object = $class::load($keyProperties->$keyColumn, $this->db);
            }
            $object->delete();
        }
        $db->commit();
    }

    public static function listObjectTypesAvailableForPurge()
    {
        return [
            'Basket',
            'Command',
            'CommandTemplate',
            'Dependency',
            'DirectorJob',
            'ExternalCommand',
            'HostGroup',
            'HostTemplate',
            'IcingaTemplateChoiceHost',
            'IcingaTemplateChoiceService',
            'ImportSource',
            'Notification',
            'NotificationTemplate',
            'ServiceGroup',
            'ServiceSet',
            'ServiceTemplate',
            'SyncRule',
            'TimePeriod',
        ];
    }

    public static function objectTypeIsEligibleForPurge($type)
    {
        return in_array($type, static::listObjectTypesAvailableForPurge(), true);
    }

    public static function assertObjectTypesAreEligibleForPurge($types)
    {
        $invalid = [];
        foreach ($types as $type) {
            if (! static::objectTypeIsEligibleForPurge($type)) {
                $invalid[] = $type;
            }
        }

        if (empty($invalid)) {
            return;
        }

        if (count($invalid) === 1) {
            $message = sprintf('"%s" is not eligible for purge', $invalid[0]);
        } else {
            $message = 'The following types are not eligible for purge: '
                . implode(', ', $invalid);
        }

        throw new InvalidArgumentException(
            "$message. Valid types: "
            . implode(', ', static::listObjectTypesAvailableForPurge())
        );
    }

    protected function makeRowKey($row)
    {
        $row = (array) $row;
        ksort($row);
        return json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
