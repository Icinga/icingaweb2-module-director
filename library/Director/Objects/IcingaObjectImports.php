<?php

namespace Icinga\Module\Director\Objects;

use Countable;
use Exception;
use Icinga\Exception\NotFoundError;
use Iterator;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use RuntimeException;

class IcingaObjectImports implements Iterator, Countable, IcingaConfigRenderer
{
    protected $storedNames = [];

    /** @var array A list of our imports, key and value are the import name */
    protected $imports = [];

    /** @var IcingaObject[] A list of all objects we have seen, referred by name */
    protected $objects = [];

    protected $modified = false;

    /** @var IcingaObject The parent object */
    protected $object;

    private $position = 0;

    protected $idx = [];

    public function __construct(IcingaObject $object)
    {
        $this->object = $object;
    }

    #[\ReturnTypeWillChange]
    public function count()
    {
        return count($this->imports);
    }

    #[\ReturnTypeWillChange]
    public function rewind()
    {
        $this->position = 0;
    }

    public function setModified()
    {
        $this->modified = true;
        return $this;
    }

    public function hasBeenModified()
    {
        return $this->modified;
    }

    /**
     * @return IcingaObject|null
     * @throws \Icinga\Exception\NotFoundError
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        if (! $this->valid()) {
            return null;
        }

        return $this->getObject(
            $this->imports[$this->idx[$this->position]]
        );
    }

    #[\ReturnTypeWillChange]
    public function key()
    {
        return $this->idx[$this->position];
    }

    #[\ReturnTypeWillChange]
    public function next()
    {
        ++$this->position;
    }

    #[\ReturnTypeWillChange]
    public function valid()
    {
        return array_key_exists($this->position, $this->idx);
    }

    /**
     * @param $key
     * @return IcingaObject|null
     * @throws \Icinga\Exception\NotFoundError
     */
    public function get($key)
    {
        if (array_key_exists($key, $this->imports)) {
            return $this->getObject($this->imports[$key]);
        }

        return null;
    }

    public function set($import)
    {
        if (empty($import)) {
            if (empty($this->imports)) {
                return $this;
            } else {
                return $this->clear();
            }
        }

        if (! is_array($import)) {
            $import = [$import];
        }

        $existing = $this->listImportNames();
        $new = $this->listNamesForGivenImports($import);

        if ($existing === $new) {
            return $this;
        }

        $this->imports = [];
        return $this->add($import);
    }

    protected function listNamesForGivenImports($imports)
    {
        $list = [];
        $class = $this->getImportClass();

        foreach ($imports as $i) {
            if ($i instanceof $class) {
                $list[] = $i->object_name;
            } else {
                $list[] = $i;
            }
        }

        return $list;
    }

    /**
     * Magic isset check
     *
     * @param string $import
     *
     * @return boolean
     */
    public function __isset($import)
    {
        return array_key_exists($import, $this->imports);
    }

    public function clear()
    {
        if ($this->imports === []) {
            return $this;
        }

        $this->imports = [];
        $this->modified = true;

        return $this->refreshIndex();
    }

    public function remove($import)
    {
        if (array_key_exists($import, $this->imports)) {
            unset($this->imports[$import]);
        }

        $this->modified = true;

        return $this->refreshIndex();
    }

    protected function refreshIndex()
    {
        $this->idx = array_keys($this->imports);
        // $this->object->templateResolver()->refreshObject($this->object);

        return $this;
    }

    public function add($import)
    {
        $class = $this->getImportClass();

        if (is_array($import)) {
            foreach ($import as $i) {
                // Gracefully ignore null members or empty strings
                if (! $i instanceof $class && strlen($i) === 0) {
                    continue;
                }

                $this->add($i);
            }

            return $this;
        }

        if ($import instanceof $class) {
            $name = $import->object_name;
            if (array_key_exists($name, $this->imports)) {
                return $this;
            }

            $this->imports[$name] = $name;
            $this->objects[$name] = $import;
        } elseif (is_string($import)) {
            if (array_key_exists($import, $this->imports)) {
                return $this;
            }

            $this->imports[$import] = $import;
        }

        $this->modified = true;
        $this->refreshIndex();

        return $this;
    }

    /**
     * @return IcingaObject[]
     * @throws \Icinga\Exception\NotFoundError
     */
    public function getObjects()
    {
        $list = [];
        foreach ($this->listImportNames() as $name) {
            $name = (string) $name;
            $list[$name] = $this->getObject($name);
        }

        return $list;
    }

    /**
     * @param $name
     * @return IcingaObject
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function getObject($name)
    {
        if (array_key_exists($name, $this->objects)) {
            return $this->objects[$name];
        }

        $connection = $this->object->getConnection();
        /** @var IcingaObject $class */
        $class = $this->getImportClass();
        try {
            if (is_array($this->object->getKeyName())) {
                // Services only
                $import = $class::load([
                    'object_name' => $name,
                    'object_type' => 'template'
                ], $connection);
            } else {
                $import = $class::load($name, $connection);
            }
        } catch (NotFoundError $e) {
            throw new NotFoundError(sprintf(
                'Unable to load parent referenced from %s "%s", %s',
                $this->object->getShortTableName(),
                $this->object->getObjectName(),
                lcfirst($e->getMessage())
            ), $e->getCode(), $e);
        }

        return $this->objects[$import->getObjectName()] = $import;
    }

    protected function getImportTableName()
    {
        return $this->object->getTableName() . '_inheritance';
    }

    public function listImportNames()
    {
        return array_keys($this->imports);
    }

    public function listOriginalImportNames()
    {
        return $this->storedNames;
    }

    public function getType()
    {
        return $this->object->getShortTableName();
    }

    protected function loadFromDb()
    {
        // $resolver = $this->object->templateResolver();
        // $this->objects = $resolver->fetchParents();
        $this->objects = IcingaTemplateRepository::instanceByObject($this->object)
            ->getTemplatesIndexedByNameFor($this->object);
        if (empty($this->objects)) {
            $this->imports = [];
        } else {
            $keys = array_keys($this->objects);
            $this->imports = array_combine($keys, $keys);
        }

        $this->setBeingLoadedFromDb();
        return $this;
    }

    /**
     * @return bool
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Icinga\Exception\NotFoundError
     */
    public function store()
    {
        if (! $this->hasBeenModified()) {
            return true;
        }

        $objectId = $this->object->get('id');
        if ($objectId === null) {
            throw new RuntimeException(
                'Cannot store imports for unstored object with no ID'
            );
        } else {
            $objectId = (int) $objectId;
        }

        $type = $this->getType();

        $objectCol = $type . '_id';
        $importCol = 'parent_' . $type . '_id';
        $table = $this->getImportTableName();
        $db = $this->object->getDb();

        if ($this->object->hasBeenLoadedFromDb()) {
            $db->delete(
                $table,
                $objectCol . ' = ' . $objectId
            );
        }

        $weight = 1;
        foreach ($this->getObjects() as $import) {
            $db->insert($table, [
                $objectCol => $objectId,
                $importCol => $import->get('id'),
                'weight'   => $weight++
            ]);
        }

        $this->setBeingLoadedFromDb();

        return true;
    }

    public function setBeingLoadedFromDb()
    {
        $this->storedNames = $this->listImportNames();
        $this->modified = false;
    }

    protected function getImportClass()
    {
        return get_class($this->object);
    }

    public static function loadForStoredObject(IcingaObject $object)
    {
        $obj = new static($object);
        return $obj->loadFromDb();
    }

    public function toConfigString()
    {
        $ret = '';

        foreach ($this->listImportNames() as $name) {
            $ret .= '    import ' . c::renderString($name) . "\n";
        }

        if ($ret !== '') {
            $ret .= "\n";
        }
        return $ret;
    }

    public function toLegacyConfigString()
    {
        $ret = '';

        foreach ($this->listImportNames() as $name) {
            $ret .= c1::renderKeyValue('use', c1::renderString($name)) . "\n";
        }

        if ($ret !== '') {
            $ret .= "\n";
        }
        return $ret;
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

    public function __destruct()
    {
        unset($this->object);
        unset($this->objects);
    }
}
