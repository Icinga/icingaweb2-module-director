<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use Icinga\Module\Director\Objects\IcingaObject;
use Iterator;
use Countable;

class CustomVariables implements Iterator, Countable, IcingaConfigRenderer
{
    protected $storedVars = array();

    protected $vars = array();

    protected $modified = false;

    private $position = 0;

    protected $idx = array();

    public function count()
    {
        $count = 0;
        foreach ($this->vars as $var) {
            if (! $var->hasBeenDeleted()) {
                $count++;
            }
        }

        return $count;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function current()
    {
        if (! $this->valid()) {
            return null;
        }

        return $this->vars[$this->idx[$this->position]];
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


    /**
     * Generic setter
     *
     * @param string $property
     * @param mixed  $value
     *
     * @return array
     */
    public function set($key, $value)
    {
        $key = (string) $key;

        if ($value instanceof CustomVariable) {
            $value = clone($value);
        } else {
            $value = CustomVariable::create($key, $value);
        }

        // Hint: isset($this->$key) wouldn't conflict with protected properties
        if ($this->__isset($key)) {
            if ($value->equals($this->get($key))) {
                return $this;
            } else {
                if (get_class($this->vars[$key]) === get_class($value)) {
                    $this->vars[$key]->setValue($value->getValue())->setModified();
                } else {
                    $this->vars[$key] = $value->setLoadedFromDb()->setModified();
                }
            }
        } else {
            $this->vars[$key] = $value->setModified();
        }

        $this->modified = true;
        $this->refreshIndex();

        return $this;
    }

    protected function refreshIndex()
    {
        $this->idx = array();
        foreach ($this->vars as $name => $var) {
            if (! $var->hasBeenDeleted()) {
                $this->idx[] = $name;
            }
        }
    }

    public static function loadForStoredObject(IcingaObject $object)
    {
        $db    = $object->getDb();

        $query = $db->select()->from(
            array('v' => $object->getVarsTableName()),
            array(
                'v.varname',
                'v.varvalue',
                'v.format',
            )
        )->where(sprintf('v.%s = ?', $object->getVarsIdColumn()), $object->id);

        $vars = new CustomVariables;
        foreach ($db->fetchAll($query) as $row) {
            $vars->vars[$row->varname] = CustomVariable::fromDbRow($row);
        }
        $vars->refreshIndex();
        $vars->setUnmodified();
        return $vars;
    }

    public function storeToDb(IcingaObject $object)
    {
        $db            = $object->getDb();
        $table         = $object->getVarsTableName();
        $foreignColumn = $object->getVarsIdColumn();
        $foreignId     = $object->id;


        foreach ($this->vars as $var) {
            if ($var->isNew()) {
                $db->insert(
                    $table,
                    array(
                        $foreignColumn => $foreignId,
                        'varname'      => $var->getKey(),
                        'varvalue'     => $var->getDbValue(),
                        'format'       => $var->getDbFormat()
                    )
                );
                $var->setLoadedFromDb();
                continue;
            }

            $where = $db->quoteInto(sprintf('%s = ?', $foreignColumn), (int) $foreignId)
                   . $db->quoteInto(' AND varname = ?', $var->getKey());

            if ($var->hasBeenDeleted()) {
                $db->delete($table, $where);
            } elseif ($var->hasBeenModified()) {
                $db->update(
                    $table,
                    array(
                        'varvalue' => $var->getDbValue(),
                        'format'   => $var->getDbFormat()
                    ),
                    $where
                );
            }
        }

        $this->setUnmodified();
    }

    public function get($key)
    {
        if (array_key_exists($key, $this->vars)) {
            return $this->vars[$key];
        }

        return null;
    }

    public function hasBeenModified()
    {
        if ($this->modified) {
            return true;
        }

        foreach ($this->vars as $var) {
            if ($var->hasBeenModified()) {
                return true;
            }
        }

        return false;
    }

    public function setUnmodified()
    {
        $this->modified = false;
        $this->storedVars = array();
        foreach ($this->vars as $key => $var) {
            $this->storedVars[$key] = clone($var);
            $var->setUnmodified();
        }
        return $this;
    }

    public function getOriginalVars()
    {
        return $this->storedVars;
    }

    public function toConfigString()
    {
        $out = '';

        foreach ($this->vars as $key => $var) {
            $out .= c::renderKeyValue(
                'vars.' . c::escapeIfReserved($key),
                $var->toConfigString()
            );
        }

        return $out;
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Magic setter
     *
     * @param  string  $key  Key
     * @param  mixed   $val  Value
     *
     * @return void
     */
    public function __set($key, $val)
    {
        $this->set($key, $val);
    }

    /**
     * Magic isset check
     *
     * @return boolean
     */
    public function __isset($key)
    {
        return array_key_exists($key, $this->vars);
    }

    /**
     * Magic unsetter
     *
     * @return void
     */
    public function __unset($key)
    {
        if (! array_key_exists($key, $this->vars)) {
            return;
        }

        $this->vars[$key]->delete();
        $this->modified = true;

        $this->refreshIndex();
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
            call_user_func($previousHandler, $e);
            die();
        }
    }
}
