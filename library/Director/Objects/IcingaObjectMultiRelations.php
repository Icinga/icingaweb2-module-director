<?php

namespace Icinga\Module\Director\Objects;

use Exception;
use Icinga\Exception\ProgrammingError;
use Iterator;
use Countable;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;

class IcingaObjectMultiRelations implements Iterator, Countable, IcingaConfigRenderer
{
    protected $stored = array();

    protected $relations = array();

    protected $modified = false;

    protected $object;

    protected $propertyName;

    protected $relatedObjectClass;

    protected $relatedTableName;

    protected $relationIdColumn;

    protected $relatedShortName;

    protected $legacyPropertyName;

    private $position = 0;

    private $db;

    protected $idx = array();

    public function __construct(IcingaObject $object, $propertyName, $config)
    {
        $this->object = $object;
        $this->propertyName = $propertyName;

        if (is_object($config) || is_array($config)) {
            foreach ($config as $k => $v) {
                $this->$k = $v;
            }
        } else {
            $this->relatedObjectClass = $config;
        }
    }

    public function getObjects()
    {
        return $this->relations;
    }

    public function count()
    {
        return count($this->relations);
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function hasBeenModified()
    {
        return $this->modified;
    }

    public function current()
    {
        if (! $this->valid()) {
            return null;
        }

        return $this->relations[$this->idx[$this->position]];
    }

    public function key()
    {
        return $this->idx[$this->position];
    }

    public function next()
    {
        ++$this->position;
    }

    public function valid()
    {
        return array_key_exists($this->position, $this->idx);
    }

    public function get($key)
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        return null;
    }

    public function set($relation)
    {
        if (! is_array($relation)) {
            if ($relation === null) {
                $relation = array();
            } else {
                $relation = array($relation);
            }
        }

        $existing = array_keys($this->relations);
        $new = array();
        $class = $this->getRelatedClassName();
        $unset = array();

        foreach ($relation as $k => $ro) {
            if ($ro instanceof $class) {
                $new[] = $ro->object_name;
            } else {
                if (empty($ro)) {
                    $unset[] = $k;
                    continue;
                }

                $new[] = $ro;
            }
        }

        foreach ($unset as $k) {
            unset($relation[$k]);
        }

        sort($existing);
        sort($new);
        if ($existing === $new) {
            return $this;
        }

        $this->relations = array();
        if (empty($relation)) {
            $this->modified = true;
            $this->refreshIndex();
            return $this;
        }

        return $this->add($relation);
    }

    /**
     * Magic isset check
     *
     * @return boolean
     */
    public function __isset($relation)
    {
        return array_key_exists($relation, $this->relations);
    }

    public function remove($relation)
    {
        if (array_key_exists($relation, $this->relations)) {
            unset($this->relations[$relation]);
        }

        $this->modified = true;
        $this->refreshIndex();
    }

    protected function refreshIndex()
    {
        ksort($this->relations);
        $this->idx = array_keys($this->relations);
    }

    public function add($relation, $onError = 'fail')
    {
        // TODO: only one query when adding array
        if (is_array($relation)) {
            foreach ($relation as $r) {
                $this->add($r, $onError);
            }
            return $this;
        }

        if (array_key_exists($relation, $this->relations)) {
            return $this;
        }

        $class = $this->getRelatedClassName();

        if ($relation instanceof $class) {
            $this->relations[$relation->object_name] = $relation;
        } elseif (is_string($relation)) {
            $connection = $this->object->getConnection();
            try {
                // Related services can only be objects, used by ServiceSets
                if ($class === 'Icinga\\Module\\Director\\Objects\\IcingaService') {
                    $relation = $class::load(array(
                        'object_name' => $relation,
                        'object_type' => 'template'
                    ), $connection);
                } else {
                    $relation = $class::load($relation, $connection);
                }
            } catch (Exception $e) {
                switch ($onError) {
                    case 'autocreate':
                        $relation = $class::create(array(
                            'object_type' => 'object',
                            'object_name' => $relation
                        ));
                        $relation->store($connection);
                        // TODO
                    case 'fail':
                        throw new ProgrammingError(
                            'The related %s "%s" doesn\'t exists: %s',
                            $this->getRelatedTableName(),
                            $relation,
                            $e->getMessage()
                        );
                        break;
                    case 'ignore':
                        return $this;
                }
            }
        } else {
            throw new ProgrammingError(
                'Invalid related object: %s',
                var_export($relation, 1)
            );
        }

        $this->relations[$relation->object_name] = $relation;
        $this->modified = true;
        $this->refreshIndex();

        return $this;
    }

    protected function getPropertyName()
    {
        return $this->propertyName;
    }

    protected function getRelatedShortName()
    {
        if ($this->relatedShortName === null) {
            /** @var IcingaObject $class */
            $class = $this->getRelatedClassName();
            $this->relatedShortName = $class::create()->getShortTableName();
        }

        return $this->relatedShortName;
    }

    protected function getTableName()
    {
        return $this->object->getTableName() . '_' . $this->getRelatedShortName();
    }

    protected function getRelatedTableName()
    {
        if ($this->relatedTableName === null) {
            /** @var IcingaObject $class */
            $class = $this->getRelatedClassName();
            $this->relatedTableName = $class::create()->getTableName();
        }

        return $this->relatedTableName;
    }

    protected function getRelationIdColumn()
    {
        if ($this->relationIdColumn === null) {
            $this->relationIdColumn = $this->getRelatedShortName();
        }

        return $this->relationIdColumn;
    }

    public function listRelatedNames()
    {
        return array_keys($this->relations);
    }

    public function listOriginalNames()
    {
        return array_keys($this->stored);
    }

    public function getType()
    {
        return $this->object->getShortTableName();
    }

    protected function loadFromDb()
    {
        $db = $this->getDb();
        $connection = $this->object->getConnection();

        $type = $this->getType();
        $objectIdCol = $type . '_id';
        $relationIdCol = $this->getRelationIdColumn() . '_id';

        $query = $db->select()->from(
            array('r' => $this->getTableName()),
            array()
        )->join(
            array('ro' => $this->getRelatedTableName()),
            sprintf('r.%s = ro.id', $relationIdCol),
            '*'
        )->where(
            sprintf('r.%s = ?', $objectIdCol),
            (int) $this->object->id
        )->order('ro.object_name');

        $class = $this->getRelatedClassName();
        $this->relations = $class::loadAll($connection, $query, 'object_name');
        $this->setBeingLoadedFromDb();

        return $this;
    }

    public function store()
    {
        $db = $this->getDb();
        $stored = array_keys($this->stored);
        $relations = array_keys($this->relations);

        $objectId = $this->object->id;
        $type = $this->getType();
        $objectCol = $type . '_id';
        $relationCol = $this->getRelationIdColumn() . '_id';

        $toDelete = array_diff($stored, $relations);
        foreach ($toDelete as $relation) {
            // We work with cloned objects. (why?)
            // As __clone drops the id, we need to access original properties
            $orig =  $this->stored[$relation]->getOriginalProperties();
            $where = sprintf(
                $objectCol . ' = %d AND ' . $relationCol . ' = %d',
                $objectId,
                $orig['id']
            );

            $db->delete(
                $this->getTableName(),
                $where
            );
        }

        $toAdd = array_diff($relations, $stored);
        foreach ($toAdd as $related) {
            $db->insert(
                $this->getTableName(),
                array(
                    $objectCol => $objectId,
                    $relationCol => $this->relations[$related]->id
                )
            );
        }
        $this->setBeingLoadedFromDb();

        return true;
    }

    public function setBeingLoadedFromDb()
    {
        $this->stored = array();
        foreach ($this->relations as $k => $v) {
            $this->stored[$k] = clone($v);
        }
    }

    protected function getRelatedClassName()
    {
        return __NAMESPACE__ . '\\' . $this->relatedObjectClass;
    }

    protected function getDb()
    {
        if ($this->db === null) {
            $this->db = $this->object->getDb();
        }

        return $this->db;
    }

    public static function loadForStoredObject(IcingaObject $object, $propertyName, $relatedObjectClass)
    {
        $relations = new static($object, $propertyName, $relatedObjectClass);
        return $relations->loadFromDb();
    }

    public function toConfigString()
    {
        $relations = array_keys($this->relations);

        if (empty($relations)) {
            return '';
        }

        return c::renderKeyValue($this->propertyName, c::renderArray($relations));
    }

    public function __toString()
    {
        try {
            return $this->toConfigString();
        } catch (Exception $e) {
            trigger_error($e);
            $previousHandler = set_exception_handler(
                function () {
                }
            );
            restore_error_handler();
            if ($previousHandler !== null) {
                call_user_func($previousHandler, $e);
                die();
            } else {
                die($e->getMessage());
            }
        }
    }

    public function toLegacyConfigString()
    {
        $relations = array_keys($this->relations);

        if (empty($relations)) {
            return '';
        }

        if ($this->legacyPropertyName === null) {
            return '    # not supported in legacy: ' .
                c1::renderKeyValue($this->propertyName, c1::renderArray($relations), '');
        }

        return c1::renderKeyValue($this->legacyPropertyName, c1::renderArray($relations));
    }
}
