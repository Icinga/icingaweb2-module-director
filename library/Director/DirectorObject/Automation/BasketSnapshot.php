<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Core\Json;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Objects\IcingaObject;
use RuntimeException;

class BasketSnapshot extends DbObject
{
    protected $objects = [];

    protected $content;

    protected $table = 'director_basket_snapshot';

    protected $keyName = [
        'basket_uuid',
        'ts_create',
    ];

    protected $defaultProperties = [
        'basket_uuid'      => null,
        'content_checksum' => null,
        'ts_create'        => null,
    ];

    public static function getClassForType($type)
    {
        $types = [
            'ImportSource'    => '\\Icinga\\Module\\Director\\Objects\\ImportSource',
            'SyncRule'        => '\\Icinga\\Module\\Director\\Objects\\SyncRule',
            'DirectorJob'     => '\\Icinga\\Module\\Director\\Objects\\DirectorJob',
            'ServiceSet'      => '\\Icinga\\Module\\Director\\Objects\\IcingaServiceSet',
            'HostTemplate'    => '\\Icinga\\Module\\Director\\Objects\\IcingaHost',
            'ServiceTemplate' => '\\Icinga\\Module\\Director\\Objects\\IcingaService',
            'Basket'          => '\\Icinga\\Module\\Director\\DirectorObject\\Automation\\Automation',
        ];

        return $types[$type];
    }

    public static function createForBasket(Basket $basket, Db $db)
    {
        $snapshot = static::create([
            'basket_uuid' => $basket->get('uuid')
        ], $db);
        $snapshot->addObjectsChosenByBasket($basket);

        return $snapshot;
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
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function restoreTo(Db $connection, $replace = true)
    {
        $all = Json::decode($this->getJsonDump());
        $db = $connection->getDbAdapter();
        $db->beginTransaction();
        foreach ($all as $typeName => $objects) {
            $class = static::getClassForType($typeName);
            foreach ($objects as $object) {
                /** @var DbObject $new */
                $new = $class::import($object, $connection, $replace);
                if ($new->hasBeenModified()) {
                    $new->store();
                }
            }
        }
        $db->commit();
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

    public function getJsonSummary()
    {
        if ($this->hasBeenLoadedFromDb()) {
            return $this->getContent()->get('summary');
        } else {
            return Json::encode($this->getSummary(), JSON_PRETTY_PRINT);
        }
    }

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

    public function getJsonDump()
    {
        if ($this->hasBeenLoadedFromDb()) {
            return $this->getContent()->get('content');
        } else {
            return Json::encode($this->objects, JSON_PRETTY_PRINT);
        }
    }

    protected static function classWantsTemplate($class)
    {
        return strpos($class, '\\Icinga\\Module\\Director\\Objects\\Icinga') === 0;
    }

    protected function addAll($typeName)
    {
        $class = static::getClassForType($typeName);
        /** @var ExportInterface $object */
        if (static::classWantsTemplate($class)) {
            /** @var IcingaObject $dummy */
            $dummy = $class::create();
            $db = $this->getDb();
            $select = $db->select()->from($dummy->getTableName())
                ->where('object_type = ?', 'template');
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
     * @return ExportInterface
     */
    public static function instanceByIdentifier($typeName, $identifier, Db $connection)
    {
        $class = static::getClassForType($typeName);
        if (static::classWantsTemplate($class)) {
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

    protected function addByIdentifier($typeName, $identifier)
    {
        $object = static::instanceByIdentifier(
            $typeName,
            $identifier,
            $this->getConnection()
        );
        $this->objects[$typeName][$identifier] = $object->export();
    }
}
