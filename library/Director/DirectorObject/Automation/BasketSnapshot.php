<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaDependency;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaHostGroup;
use Icinga\Module\Director\Objects\IcingaNotification;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceGroup;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Objects\IcingaTemplateChoiceHost;
use Icinga\Module\Director\Objects\IcingaTemplateChoiceService;
use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;
use InvalidArgumentException;
use RuntimeException;

class BasketSnapshot extends DbObject
{
    protected static $typeClasses = [
        'Datafield'       => DirectorDatafield::class,
        'TimePeriod'      => IcingaTimePeriod::class,
        'CommandTemplate' => [IcingaCommand::class, ['object_type' => 'template']],
        'ExternalCommand' => [IcingaCommand::class, ['object_type' => 'external_object']],
        'Command'         => [IcingaCommand::class, ['object_type' => 'object']],
        'HostGroup'       => IcingaHostGroup::class,
        'IcingaTemplateChoiceHost' => IcingaTemplateChoiceHost::class,
        'HostTemplate'    => IcingaHost::class,
        'ServiceGroup'    => IcingaServiceGroup::class,
        'IcingaTemplateChoiceService' => IcingaTemplateChoiceService::class,
        'ServiceTemplate' => IcingaService::class,
        'ServiceSet'      => IcingaServiceSet::class,
        'NotificationTemplate' => IcingaNotification::class,
        'Notification'    => IcingaNotification::class,
        'DataList'        => DirectorDatalist::class,
        'Dependency'      => IcingaDependency::class,
        'ImportSource'    => ImportSource::class,
        'SyncRule'        => SyncRule::class,
        'DirectorJob'     => DirectorJob::class,
        'Basket'          => Basket::class,
    ];

    protected $objects = [];

    protected $content;

    protected $table = 'director_basket_snapshot';

    protected $keyName = [
        'basket_uuid',
        'ts_create',
    ];

    protected $restoreOrder = [
        'CommandTemplate',
        'ExternalCommand',
        'Command',
        'TimePeriod',
        'HostGroup',
        'IcingaTemplateChoiceHost',
        'HostTemplate',
        'ServiceGroup',
        'IcingaTemplateChoiceService',
        'ServiceTemplate',
        'ServiceSet',
        'NotificationTemplate',
        'Notification',
        'Dependency',
        'ImportSource',
        'SyncRule',
        'DirectorJob',
        'Basket',
    ];

    protected $defaultProperties = [
        'basket_uuid'      => null,
        'content_checksum' => null,
        'ts_create'        => null,
    ];

    protected $binaryProperties = [
        'basket_uuid',
        'content_checksum',
    ];

    public static function supports($type)
    {
        return isset(self::$typeClasses[$type]);
    }

    public static function assertValidType($type)
    {
        if (! static::supports($type)) {
            throw new InvalidArgumentException("Basket does not support '$type'");
        }
    }

    public static function getClassForType($type)
    {
        static::assertValidType($type);

        if (is_array(self::$typeClasses[$type])) {
            return self::$typeClasses[$type][0];
        } else {
            return self::$typeClasses[$type];
        }
    }

    /**
     * @param Basket $basket
     * @param Db $db
     * @return BasketSnapshot
     * @throws \Icinga\Exception\NotFoundError
     */
    public static function createForBasket(Basket $basket, Db $db)
    {
        $snapshot = static::create([
            'basket_uuid' => $basket->get('uuid')
        ], $db);
        $snapshot->addObjectsChosenByBasket($basket);
        $snapshot->resolveRequiredFields();

        return $snapshot;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function resolveRequiredFields()
    {
        /** @var Db $db */
        $db = $this->getConnection();
        $fieldResolver = new BasketSnapshotFieldResolver($this->objects, $db);
        /** @var DirectorDatafield[] $fields */
        $fields = $fieldResolver->loadCurrentFields($db);
        if (! empty($fields)) {
            $plain = [];
            foreach ($fields as $id => $field) {
                $plain[$id] = $field->export();
            }
            $this->objects['Datafield'] = $plain;
        }
    }

    protected function addObjectsChosenByBasket(Basket $basket)
    {
        foreach ($basket->getChosenObjects() as $typeName => $selection) {
            if ($selection === true) {
                $this->addAll($typeName);
            } elseif (! empty($selection)) {
                $this->addByIdentifiers($typeName, $selection);
            }
        }
    }

    /**
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function beforeStore()
    {
        if ($this->hasBeenLoadedFromDb()) {
            throw new RuntimeException('A basket snapshot cannot be modified');
        }
        $json = $this->getJsonDump();
        $checksum = sha1($json, true);
        if (! BasketContent::exists($checksum, $this->getConnection())) {
            BasketContent::create([
                'checksum' => $checksum,
                'summary'  => $this->getJsonSummary(),
                'content'  => $json,
            ], $this->getConnection())->store();
        }

        $this->set('content_checksum', $checksum);
        $this->set('ts_create', round(microtime(true) * 1000));
    }

    /**
     * @param Db $connection
     * @param bool $replace
     * @throws \Icinga\Exception\NotFoundError
     */
    public function restoreTo(Db $connection, $replace = true)
    {
        static::restoreJson(
            $this->getJsonDump(),
            $connection,
            $replace
        );
    }

    /**
     * @param Basket $basket
     * @param $string
     * @return BasketSnapshot
     */
    public static function forBasketFromJson(Basket $basket, $string)
    {
        $snapshot = static::create([
            'basket_uuid' => $basket->get('uuid')
        ]);
        $snapshot->objects = [];
        foreach ((array) Json::decode($string) as $type => $objects) {
            $snapshot->objects[$type] = (array) $objects;
        }

        return $snapshot;
    }

    public static function restoreJson($string, Db $connection, $replace = true)
    {
        $snapshot = new static();
        $snapshot->restoreObjects(
            Json::decode($string),
            $connection,
            $replace
        );
    }

    /**
     * @param $all
     * @param Db $connection
     * @param bool $replace
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function restoreObjects($all, Db $connection, $replace = true)
    {
        $db = $connection->getDbAdapter();
        $db->beginTransaction();
        $fieldResolver = new BasketSnapshotFieldResolver($all, $connection);
        $this->restoreType($all, 'DataList', $fieldResolver, $connection, $replace);
        $fieldResolver->storeNewFields();
        foreach ($this->restoreOrder as $typeName) {
            $this->restoreType($all, $typeName, $fieldResolver, $connection, $replace);
        }
        $db->commit();
    }

    /**
     * @param $all
     * @param $typeName
     * @param BasketSnapshotFieldResolver $fieldResolver
     * @param Db $connection
     * @param $replace
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function restoreType(
        &$all,
        $typeName,
        BasketSnapshotFieldResolver $fieldResolver,
        Db $connection,
        $replace
    ) {
        if (isset($all->$typeName)) {
            $objects = (array) $all->$typeName;
        } else {
            return;
        }
        $class = static::getClassForType($typeName);

        $changed = [];
        foreach ($objects as $key => $object) {
            /** @var DbObject $new */
            $new = $class::import($object, $connection, $replace);
            if ($new->hasBeenModified()) {
                if ($new instanceof IcingaObject && $new->supportsImports()) {
                    /** @var ExportInterface $new */
                    $changed[$new->getUniqueIdentifier()] = $new;
                } else {
                    $new->store();
                    // Linking fields right now, as we're not in $changed
                    if ($new instanceof IcingaObject) {
                        $fieldResolver->relinkObjectFields($new, $object);
                    }
                }
            } else {
                // No modification on the object, still, fields might have
                // been changed
                if ($new instanceof IcingaObject) {
                    $fieldResolver->relinkObjectFields($new, $object);
                }
            }
            $allObjects[spl_object_hash($new)] = $object;
        }

        /** @var IcingaObject $object */
        foreach ($changed as $object) {
            $this->recursivelyStore($object, $changed);
        }
        foreach ($changed as $key => $new) {
            // Store related fields. As objects might have formerly been
            // un-stored, let's to it right here
            if ($new instanceof IcingaObject) {
                $fieldResolver->relinkObjectFields($new, $objects[$key]);
            }
        }
    }

    /**
     * @param IcingaObject $object
     * @param $list
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    protected function recursivelyStore(IcingaObject $object, &$list)
    {
        foreach ($object->listImportNames() as $parent) {
            if (array_key_exists($parent, $list)) {
                $this->recursivelyStore($list[$parent], $list);
            }
        }

        $object->store();
    }

    /**
     * @return BasketContent
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getContent()
    {
        if ($this->content === null) {
            $this->content = BasketContent::load($this->get('content_checksum'), $this->getConnection());
        }

        return $this->content;
    }

    protected function onDelete()
    {
        $db = $this->getDb();
        $db->delete(
            ['bc' => 'director_basket_content'],
            'NOT EXISTS (SELECT director_basket_checksum WHERE content_checksum = bc.checksum)'
        );
    }

    /**
     * @return string
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getJsonSummary()
    {
        if ($this->hasBeenLoadedFromDb()) {
            return $this->getContent()->get('summary');
        } else {
            return Json::encode($this->getSummary(), JSON_PRETTY_PRINT);
        }
    }

    /**
     * @return array|mixed
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getSummary()
    {
        if ($this->hasBeenLoadedFromDb()) {
            return Json::decode($this->getContent()->get('summary'));
        } else {
            $summary = [];
            foreach (array_keys($this->objects) as $key) {
                $summary[$key] = count($this->objects[$key]);
            }

            return $summary;
        }
    }

    /**
     * @return string
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getJsonDump()
    {
        if ($this->hasBeenLoadedFromDb()) {
            return $this->getContent()->get('content');
        } else {
            return Json::encode($this->objects, JSON_PRETTY_PRINT);
        }
    }

    protected function addAll($typeName)
    {
        $class = static::getClassForType($typeName);
        if (is_array(self::$typeClasses[$typeName])) {
            $filter = self::$typeClasses[$typeName][1];
        } else {
            $filter = null;
        }
        /** @var IcingaObject $dummy */
        $dummy = $class::create();
        /** @var ExportInterface $object */
        if ($dummy instanceof IcingaObject && $dummy->supportsImports()) {
            $db = $this->getDb();
            $select = $db->select()->from($dummy->getTableName());
            if ($filter) {
                foreach ($filter as $column => $value) {
                    $select->where("$column = ?", $value);
                }
            } elseif (! $dummy->isGroup()
                // TODO: this is ugly.
                && ! $dummy instanceof IcingaDependency
                && ! $dummy instanceof IcingaTimePeriod
            ) {
                $select->where('object_type = ?', 'template');
            }
            $all = $class::loadAll($this->getConnection(), $select);
        } else {
            $all = $class::loadAll($this->getConnection());
        }
        foreach ($all as $object) {
            $this->objects[$typeName][$object->getUniqueIdentifier()] = $object->export();
        }
    }

    protected function addByIdentifiers($typeName, $identifiers)
    {
        foreach ($identifiers as $identifier) {
            $this->addByIdentifier($typeName, $identifier);
        }
    }

    /**
     * @param $typeName
     * @param $identifier
     * @param Db $connection
     * @return ExportInterface|null
     */
    public static function instanceByIdentifier($typeName, $identifier, Db $connection)
    {
        $class = static::getClassForType($typeName);
        if (substr($class, -13) === 'IcingaService') {
            $identifier = [
                'object_type' => 'template',
                'object_name' => $identifier,
            ];
        }
        /** @var ExportInterface $object */
        if ($class::exists($identifier, $connection)) {
            $object = $class::load($identifier, $connection);
        } else {
            $object = null;
        }

        return $object;
    }

    /**
     * @param $typeName
     * @param $identifier
     */
    protected function addByIdentifier($typeName, $identifier)
    {
        /** @var Db $connection */
        $connection = $this->getConnection();
        $object = static::instanceByIdentifier(
            $typeName,
            $identifier,
            $connection
        );
        if ($object !== null) {
            $this->objects[$typeName][$identifier] = $object->export();
        }
    }
}
