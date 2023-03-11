<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use gipfl\Json\JsonDecodeException;
use gipfl\Json\JsonEncodeException;
use gipfl\Json\JsonString;
use Icinga\Module\Director\Data\Exporter;
use Icinga\Module\Director\Data\ObjectImporter;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorDatafieldCategory;
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
use Icinga\Module\Director\Objects\IcingaUser;
use Icinga\Module\Director\Objects\IcingaUserGroup;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;
use InvalidArgumentException;
use Ramsey\Uuid\UuidInterface;
use RuntimeException;
use stdClass;

class BasketSnapshot extends DbObject
{
    protected static $typeClasses = [
        'DatafieldCategory' => DirectorDatafieldCategory::class,
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
        'UserGroup'       => IcingaUserGroup::class,
        'UserTemplate'    => [IcingaUser::class, ['object_type' => 'template']],
        'User'            => [IcingaUser::class, ['object_type' => 'object']],
        'NotificationTemplate' => IcingaNotification::class,
        'Notification'    => [IcingaNotification::class, ['object_type' => 'apply']],
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
        'UserGroup',
        'UserTemplate',
        'User',
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
        }

        return self::$typeClasses[$type];
    }

    public static function getClassAndObjectTypeForType($type)
    {
        if (is_array(self::$typeClasses[$type])) {
            return self::$typeClasses[$type];
        }

        return [self::$typeClasses[$type], null];
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
        $categories = [];
        if (! empty($fields)) {
            $plain = [];
            foreach ($fields as $id => $field) {
                $plain[$id] = $field->export();
                if ($category = $field->getCategory()) {
                    $categories[$category->get('category_name')] = $category->export();
                }
            }
            $this->objects['Datafield'] = $plain;
        }
        if (! empty($categories)) {
            $this->objects['DatafieldCategory'] = $categories;
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
        foreach ((array) JsonString::decode($string) as $type => $objects) {
            $snapshot->objects[$type] = (array) $objects;
        }

        return $snapshot;
    }

    public static function restoreJson($string, Db $connection, $replace = true)
    {
        $snapshot = new static();
        $snapshot->restoreObjects(
            JsonString::decode($string),
            $connection,
            $replace
        );
    }

    /**
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Icinga\Exception\NotFoundError
     * @throws JsonDecodeException
     */
    protected function restoreObjects(stdClass $all, Db $connection, $replace = true)
    {
        $db = $connection->getDbAdapter();
        $db->beginTransaction();
        $fieldResolver = new BasketSnapshotFieldResolver($all, $connection);
        $this->restoreType($all, 'DataList', $fieldResolver, $connection, $replace);
        $this->restoreType($all, 'DatafieldCategory', $fieldResolver, $connection, $replace);
        $fieldResolver->storeNewFields();
        foreach ($this->restoreOrder as $typeName) {
            $this->restoreType($all, $typeName, $fieldResolver, $connection, $replace);
        }
        $db->commit();
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     * @throws \Zend_Db_Adapter_Exception
     * @throws JsonDecodeException
     */
    public function restoreType(
        stdClass $all,
        string $typeName,
        BasketSnapshotFieldResolver $fieldResolver,
        Db $connection,
        bool $replace
    ) {
        if ($replace === false) {
            throw new RuntimeException('Replace flag should no longer be in use');
        }

        if (isset($all->$typeName)) {
            $objects = (array) $all->$typeName;
        } else {
            return;
        }
        $class = static::getClassForType($typeName);
        $importer = new ObjectImporter($connection);
        $changed = [];
        foreach ($objects as $object) {
            $new = $importer->import($class, $object);
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
        }

        /** @var IcingaObject $object */
        foreach ($changed as $object) {
            $this->recursivelyStore($object, $changed);
        }
        foreach ($changed as $key => $new) {
            // Store related fields. As objects might have formerly been
            // un-stored, let's do it right here
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
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getContent(): BasketContent
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
     * @throws \Icinga\Exception\NotFoundError|JsonEncodeException
     */
    public function getJsonSummary(): string
    {
        if ($this->hasBeenLoadedFromDb()) {
            return $this->getContent()->get('summary');
        }

        return JsonString::encode($this->getSummary(), JSON_PRETTY_PRINT);
    }

    /**
     * @return array|mixed
     * @throws \Icinga\Exception\NotFoundError|JsonDecodeException
     */
    public function getSummary()
    {
        if ($this->hasBeenLoadedFromDb()) {
            return JsonString::decode($this->getContent()->get('summary'));
        }

        $summary = [];
        foreach (array_keys($this->objects) as $key) {
            $summary[$key] = count($this->objects[$key]);
        }

        return $summary;
    }

    /**
     * @return string
     * @throws \Icinga\Exception\NotFoundError|JsonEncodeException
     */
    public function getJsonDump()
    {
        if ($this->hasBeenLoadedFromDb()) {
            return $this->getContent()->get('content');
        }

        try {
            return JsonString::encode($this->objects, JSON_PRETTY_PRINT);
        } catch (JsonEncodeException $e) {
            foreach ($this->objects as $type => $objects) {
                foreach ($objects as $object) {
                    try {
                        JsonString::encode($object);
                    } catch (JsonEncodeException $singleError) {
                        $dump = var_export($object, 1);
                        if (function_exists('iconv')) {
                            $dump = iconv('UTF-8', 'UTF-8//IGNORE', $dump);
                        }
                        throw new JsonEncodeException(sprintf(
                            'Failed to encode object ot type "%s": %s, %s',
                            $type,
                            $dump,
                            $singleError->getMessage()
                        ), $singleError->getCode());
                    }
                }
            }

            throw $e;
        }
    }

    protected function addAll($typeName)
    {
        list($class, $filter) = static::getClassAndObjectTypeForType($typeName);
        $connection = $this->getConnection();
        assert($connection instanceof Db);

        /** @var IcingaObject $dummy */
        $dummy = $class::create();
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
            $all = $class::loadAll($connection, $select);
        } else {
            $all = $class::loadAll($connection);
        }
        $exporter = new Exporter($connection);
        foreach ($all as $object) {
            $this->objects[$typeName][$object->getUniqueIdentifier()] = $exporter->export($object);
        }
    }

    protected function addByIdentifiers($typeName, $identifiers)
    {
        foreach ($identifiers as $identifier) {
            $this->addByIdentifier($typeName, $identifier);
        }
    }

    /**
     * @return ExportInterface|DbObject|null
     */
    public static function instanceByUuid(string $typeName, UuidInterface $uuid, Db $connection)
    {
        /** @var class-string<DbObject> $class */
        $class = static::getClassForType($typeName);
        /** @var ExportInterface $object */
        return $class::loadWithUniqueId($uuid, $connection);
    }

    /**
     * @param $typeName
     * @param $identifier
     * @param Db $connection
     * @return ExportInterface|DbObject|null
     */
    public static function instanceByIdentifier($typeName, $identifier, Db $connection)
    {
        /** @var class-string<DbObject> $class */
        $class = static::getClassForType($typeName);
        if ($class === IcingaService::class) {
            $identifier = [
                'object_type' => 'template',
                'object_name' => $identifier,
            ];
        }

        /** @var ExportInterface $object */
        return $class::loadOptional($identifier, $connection);
    }

    /**
     * @param $typeName
     * @param $identifier
     */
    protected function addByIdentifier($typeName, $identifier)
    {
        /** @var Db $connection */
        $connection = $this->getConnection();
        $exporter = new Exporter($connection);
        $object = static::instanceByIdentifier(
            $typeName,
            $identifier,
            $connection
        );
        if ($object !== null) {
            $this->objects[$typeName][$identifier] = $exporter->export($object);
        }
    }
}
