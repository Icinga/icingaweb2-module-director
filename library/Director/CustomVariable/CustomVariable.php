<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;

abstract class CustomVariable implements IcingaConfigRenderer
{
    protected $key;

    protected $value;

    protected $storedValue;

    protected $type;

    protected $modified = false;

    protected $loadedFromDb = false;

    protected $deleted = false;

    protected function __construct($key, $value = null)
    {
        $this->key = $key;
        $this->setValue($value);
    }

    public function is($type)
    {
        return $this->getType() === $type;
    }

    public function getType()
    {
        if ($this->type === null) {
            $parts = explode('\\', get_class($this));
            $class = end($parts);
            // strlen('CustomVariable') === 9
            $this->type = substr(end($parts), 9);
        }

        return $this->type;
    }

    // TODO: implement delete()
    public function hasBeenDeleted()
    {
        return $this->deleted;
    }

    public function delete()
    {
        $this->deleted = true;
        return $this;
    }

    // TODO: abstract
    public function getDbValue()
    {
        return $this->getValue();
    }

    // TODO: abstract
    public function getDbFormat()
    {
        return 'string';
    }

    public function getKey()
    {
        return $this->key;
    }

    abstract public function setValue($value);

    public function isNew()
    {
        return ! $this->loadedFromDb;
    }

    public function hasBeenModified()
    {
        return $this->modified;
    }

    public function setModified($modified = true)
    {
        $this->modified = $modified;
        if (! $this->modified) {
            if (is_object($this->value)) {
                $this->storedValue = clone($this->value);
            } else {
                $this->storedValue = $this->value;
            }
        }

        return $this;
    }

    public function setUnmodified()
    {
        return $this->setModified(false);
    }

    public function setLoadedFromDb($loaded = true)
    {
        $this->loadedFromDb = $loaded;
        return $this;
    }

    abstract public function equals(CustomVariable $var);

    public function differsFrom(CustomVariable $var)
    {
        return ! $this->equals($var);
    }

    public static function wantCustomVariable($key, $value)
    {
        if ($value instanceof CustomVariable) {
            return $value;
        }

        return self::create($key, $value);
    }

    public static function create($key, $value)
    {
        if (is_null($value)) {
            return new CustomVariableNull($key, $value);
        }

        if (is_bool($value)) {
            return new CustomVariableBoolean($key, $value);
        }

        if (is_int($value) || is_float($value)) {
            return new CustomVariableNumber($key, $value);
        }

        if (is_string($value)) {

            return new CustomVariableString($key, $value);

        } elseif (is_array($value)) {

            foreach (array_keys($value) as $k) {
                if (! (is_int($k) || ctype_digit($k))) {
                    return new CustomVariableDictionary($key, $value);
                }
            }

            return new CustomVariableArray($key, array_values($value));

        } elseif (is_object($value)) {
            // TODO: check for specific class/stdClass/interface?
            return new CustomVariableDictionary($key, $value);

        } else {
            throw new ProgrammingError('WTF (%s): %s', $key, var_export($value, 1));
        }
    }

    public static function fromDbRow($row)
    {
        switch ($row->format) {
            case 'string':
                $var = new CustomVariableString($row->varname, $row->varvalue);
                break;
            case 'json':
                $var = self::create($row->varname, json_decode($row->varvalue));
                break;
            case 'expression':
                throw new ProgrammingError(
                    'Icinga code expressions are not yet supported'
                );
            default:
                throw new ProgrammingError(
                    '%s is not a supported custom variable format',
                    $row->format
                );
        }

        $var->loadedFromDb = true;
        $var->setUnmodified();
        return $var;
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
