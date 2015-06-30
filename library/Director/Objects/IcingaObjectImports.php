<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ProgrammingError;
use Iterator;
use Countable;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaObjectImports implements Iterator, Countable, IcingaConfigRenderer
{
    protected $storedImports = array();

    protected $imports = array();

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

    public function hasBeenModified()
    {
        return $this->modified;
    }

    public function current()
    {
        if (! $this->valid()) {
            return null;
        }

        return $this->imports[$this->idx[$this->position]];
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
            return $this->imports[$key];
        }

        return null;
    }

    public function set($import)
    {
        $existing = array_keys($this->imports);
        $new = array();
        $class = $this->getImportClass();
        foreach ($import as $i) {

            if ($i instanceof $class) {
                $new[] = $i->object_name;
            } else {
                $new[] = $i;
            }
        }

        if ($existing === $new) {
            return $this;
        }

        if (count($new) === 0) {
            return $this->clear();
        }

        $this->imports = array();
        return $this->add($import);
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
        $this->imports = array();

        $this->modified = true;
        $this->refreshIndex();

        return $this;
    }

    public function remove($import)
    {
        if (array_key_exists($import, $this->imports)) {
            unset($this->imports[$import]);
        }

        $this->modified = true;
        $this->refreshIndex();

        return $this;
    }

    protected function refreshIndex()
    {
        $this->idx = array_keys($this->imports);
    }

    public function add($import)
    {
        // TODO: only one query when adding array
        if (is_array($import)) {
            foreach ($import as $i) {
                $this->add($i);
            }
            return $this;
        }

        if (array_key_exists($import, $this->imports)) {
            return $this;
        }

        $class = $this->getImportClass();
        $connection = $this->object->getConnection();

        if ($import instanceof $class) {
            $this->imports[$import->object_name] = $import;
        } elseif (is_string($import)) {
            $query = $this->object->getDb()->select()->from(
                $this->object->getTableName()
            )->where('object_name = ?', $import);
            $imports = $class::loadAll($connection, $query, 'object_name');
        }
        if (! array_key_exists($import, $imports)) {
            throw new ProgrammingError(
                'The import "%s" doesn\'t exists.',
                $import
            );
        }

        $this->imports[$import] = $imports[$import];

        $this->modified = true;
        $this->refreshIndex();

        return $this;
    }

    protected function getImportTableName()
    {
        return $this->object->getTableName() . '_inheritance';
    }

    public function listImportNames()
    {
        return array_keys($this->imports);
    }

    public function getType()
    {
        return $this->object->getShortTableName();
    }

    protected function loadFromDb()
    {
        $db = $this->object->getDb();
        $connection = $this->object->getConnection();

        $type = $this->getType();

        $table = $this->object->getTableName();
        $query = $db->select()->from(
            array('o' => $table),
            array()
        )->join(
            array('oi' => $table . '_inheritance'),
            'oi.' . $type . '_id = o.id',
            array()
        )->join(
            array('i' => $table),
            'i.id = oi.parent_' . $type . '_id',
            '*'
        )->where('o.id = ?', (int) $this->object->id)
        ->order('oi.weight');

        $class = $this->getImportClass();
        $this->imports = $class::loadAll($connection, $query, 'object_name');
        $this->storedImports = $this->imports;

        return $this;
    }

    public function store()
    {
        $objectId = $this->object->id;
        $type = $this->getType();

        $objectCol = $type . '_id';
        $importCol = 'parent_' . $type . '_id';

        if (! $this->hasBeenModified()) {
            return true;
        }

        if ($this->object->hasBeenLoadedFromDb()) {

            $this->object->db->delete($this->getImportTableName(), $objectCol . ' = ' . $objectId);
        }

        $weight = 1;
        foreach ($this->imports as $import) {
            $this->object->db->insert(
                $this->getImportTableName(),
                array(
                    $objectCol => $objectId,
                    $importCol => $import->id,
                    'weight'   => $weight++
                )
            );
        }

        $this->storedImports = $this->imports;

        return true;
    }

    protected function getImportClass()
    {
        return get_class($this->object);
    }

    public static function loadForStoredObject(IcingaObject $object)
    {
        $imports = new static($object);
        return $imports->loadFromDb();
    }

    public function toConfigString()
    {
        $ret = '';

        foreach ($this->imports as $name => & $o) {
            $ret .= '    import ' . c::renderString($o->object_name) . "\n";
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
            $previousHandler = set_exception_handler(function () {});
            restore_error_handler();
            if ($previousHandler !== null) {
                call_user_func($previousHandler, $e);
                die();
            } else {
                die($e->getMessage());
            }
        }
    }
}
