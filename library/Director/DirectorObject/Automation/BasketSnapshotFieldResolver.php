<?php

namespace Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\IcingaObject;

class BasketSnapshotFieldResolver
{
    /** @var BasketSnapshot */
    protected $snapshot;

    /** @var \Icinga\Module\Director\Data\Db\DbConnection */
    protected $targetDb;

    /** @var array|null */
    protected $requiredIds;

    protected $objects;

    /** @var int */
    protected $nextNewId = 1;

    /** @var array|null */
    protected $idMap;

    /** @var DirectorDatafield[]|null */
    protected $targetFields;

    public function __construct($objects, Db $targetDb)
    {
        $this->objects = $objects;
        $this->targetDb = $targetDb;
    }

    /**
     * @param Db $db
     * @return DirectorDatafield[]
     * @throws \Icinga\Exception\NotFoundError
     */
    public function loadCurrentFields(Db $db)
    {
        $fields = [];
        foreach ($this->getRequiredIds() as $id) {
            $fields[$id] = DirectorDatafield::loadWithAutoIncId((int) $id, $db);
        }

        return $fields;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function storeNewFields()
    {
        $this->targetFields = null; // Clear Cache
        foreach ($this->getTargetFields() as $id => $field) {
            if ($field->hasBeenModified()) {
                $field->store();
                $this->idMap[$id] = $field->get('id');
            }
        }
    }

    /**
     * @param IcingaObject $new
     * @param $object
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Zend_Db_Adapter_Exception
     */
    public function relinkObjectFields(IcingaObject $new, $object)
    {
        if (! $new->supportsFields() || ! isset($object->fields)) {
            return;
        }
        $fieldMap = $this->getIdMap();

        $objectId = (int) $new->get('id');
        $table = $new->getTableName() . '_field';
        $objectKey = $new->getShortTableName() . '_id';
        $existingFields = [];

        $db = $this->targetDb->getDbAdapter();

        foreach ($db->fetchAll(
            $db->select()->from($table)->where("$objectKey = ?", $objectId)
        ) as $mapping) {
            $existingFields[(int) $mapping->datafield_id] = $mapping;
        }
        foreach ($object->fields as $field) {
            $id = $fieldMap[(int) $field->datafield_id];
            if (isset($existingFields[$id])) {
                unset($existingFields[$id]);
            } else {
                $db->insert($table, [
                    $objectKey     => $objectId,
                    'datafield_id' => $id,
                    'is_required'  => $field->is_required,
                    'var_filter'   => $field->var_filter,
                ]);
            }
        }
        if (! empty($existingFields)) {
            $db->delete(
                $table,
                $db->quoteInto(
                    "$objectKey = $objectId AND datafield_id IN (?)",
                    array_keys($existingFields)
                )
            );
        }
    }

    /**
     * @param object $object
     * @throws \Icinga\Exception\NotFoundError
     */
    public function tweakTargetIds($object)
    {
        $forward = $this->getIdMap();
        $map = array_flip($forward);
        if (isset($object->fields)) {
            foreach ($object->fields as $field) {
                $id = $field->datafield_id;
                if (isset($map[$id])) {
                    $field->datafield_id = $map[$id];
                } else {
                    $field->datafield_id = "(NEW)";
                }
            }
        }
    }

    /**
     * @return int
     */
    protected function getNextNewId()
    {
        return $this->nextNewId++;
    }

    protected function getRequiredIds()
    {
        if ($this->requiredIds === null) {
            if (isset($this->objects['Datafield'])) {
                $this->requiredIds = array_keys($this->objects['Datafield']);
            } else {
                $ids = [];
                foreach ($this->objects as $typeName => $objects) {
                    foreach ($objects as $key => $object) {
                        if (isset($object->fields)) {
                            foreach ($object->fields as $field) {
                                $ids[$field->datafield_id] = true;
                            }
                        }
                    }
                }

                $this->requiredIds = array_keys($ids);
            }
        }

        return $this->requiredIds;
    }

    /**
     * @param $type
     * @return object[]
     */
    protected function getObjectsByType($type)
    {
        if (isset($this->objects->$type)) {
            return (array) $this->objects->$type;
        } else {
            return [];
        }
    }

    /**
     * @return DirectorDatafield[]
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getTargetFields()
    {
        if ($this->targetFields === null) {
            $this->calculateIdMap();
        }

        return $this->targetFields;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getIdMap()
    {
        if ($this->idMap === null) {
            $this->calculateIdMap();
        }

        return $this->idMap;
    }

    /**
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function calculateIdMap()
    {
        $this->idMap = [];
        $this->targetFields = [];
        foreach ($this->getObjectsByType('Datafield') as $id => $object) {
            unset($object->category_id); // Fix old baskets
            // Hint: import() doesn't store!
            $new = DirectorDatafield::import($object, $this->targetDb);
            if ($new->hasBeenLoadedFromDb()) {
                $newId = (int) $new->get('id');
            } else {
                $newId = sprintf('NEW(%s)', $this->getNextNewId());
            }
            $this->idMap[$id] = $newId;
            $this->targetFields[$id] = $new;
        }
    }
}
