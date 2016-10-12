<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ProgrammingError;
use Iterator;
use Countable;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaLegacyConfigHelper as c1;

class IcingaObjectImports implements Iterator, Countable, IcingaConfigRenderer
{
    protected $storedImports = array();

    protected $imports = array();

    protected $objects = array();

    protected $modified = false;

    protected $object;

    private $position = 0;

    protected $idx = array();

    public function __construct(IcingaObject $object)
    {
        $this->object = $object;
    }

    public function count()
    {
        return count($this->imports);
    }

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

    public function current()
    {
        if (! $this->valid()) {
            return null;
        }

        return $this->getObject(
            $this->imports[$this->idx[$this->position]]
        );
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
            $import = array($import);
        }

        $existing = $this->listImportNames();
        $new = $this->listNamesForGivenImports($import);

        if ($existing === $new) {
            return $this;
        }

        $this->imports = array();
        return $this->add($import);
    }

    protected function listNamesForGivenImports($imports)
    {
        $list = array();
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
     * @return boolean
     */
    public function __isset($import)
    {
        return array_key_exists($import, $this->imports);
    }

    public function clear()
    {
        if ($this->imports === array()) {
            return $this;
        }

        $this->imports = array();
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

    public function getObjects()
    {
        $list = array();
        foreach ($this->listImportNames() as $name) {
            $list[$name] = $this->getObject($name);
        }

        return $list;
    }

    protected function getObject($name)
    {
        if (array_key_exists($name, $this->objects)) {
            return $this->objects[$name];
        }

        $connection = $this->object->getConnection();
        $class = $this->getImportClass();
        if (is_array($this->object->getKeyName())) {
            // Services only
            $import = $class::load(
                array(
                    'object_name' => $name,
                    'object_type' => 'template'
                ),
                $connection
            );
        } else {
            $import = $class::load($name, $connection);
        }

        return $this->objects[$import->object_name] = $import;
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
        return array_keys($this->storedImports);
    }

    public function getType()
    {
        return $this->object->getShortTableName();
    }

    // TODO: prefetch
    protected function loadFromDb()
    {
        $resolver = $this->object->templateResolver();
        // Force nesting error
        $resolver->listResolvedParentIds();

        $this->objects = $resolver->fetchParents();
        $this->imports = array();
        foreach ($this->objects as $k => $obj) {
            $this->imports[$k] = $k;
        }

        $this->cloneStored();
        return $this;
    }

    protected function loadFromPrefetchCache()
    {
        $this->storedImports = $this->objects = PrefetchCache::instance()->imports($this->object);
        $this->imports = array();
        foreach ($this->objects as $o) {
            $this->imports[$o->object_name] = $o->object_name;
        }

        return $this;
    }

    public function store()
    {
        if (! $this->hasBeenModified()) {
            return true;
        }

        $objectId = (int) $this->object->id;
        $type = $this->getType();

        $objectCol = $type . '_id';
        $importCol = 'parent_' . $type . '_id';

        if ($this->object->hasBeenLoadedFromDb()) {
            $this->object->db->delete(
                $this->getImportTableName(),
                $objectCol . ' = ' . $objectId
            );
        }

        $weight = 1;
        foreach ($this->imports as $importName) {
            $import = $this->getObject($importName);
            $this->object->db->insert(
                $this->getImportTableName(),
                array(
                    $objectCol => $objectId,
                    $importCol => $import->id,
                    'weight'   => $weight++
                )
            );
        }

        $this->cloneStored();

        return true;
    }

    protected function cloneStored()
    {
        $this->storedImports = array();
        foreach ($this->objects as $k => $v) {
            $this->storedImports[$k] = clone($v);
        }
        $this->modified = false;
    }

    protected function getImportClass()
    {
        return get_class($this->object);
    }

    public static function loadForStoredObject(IcingaObject $object)
    {
        $imports = new static($object);
        if (PrefetchCache::shouldBeUsed()) {
            return $imports->loadFromPrefetchCache();
        } else {
            return $imports->loadFromDb();
        }
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
        unset($this->storedImport);
        unset($this->imports);
        unset($this->objects);
        unset($this->object);
    }
}
