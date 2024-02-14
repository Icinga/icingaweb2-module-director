<?php

namespace Icinga\Module\Director\CustomVariable;

use Exception;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;
use InvalidArgumentException;
use LogicException;

abstract class CustomVariable implements IcingaConfigRenderer
{
    protected $key;

    protected $value;

    protected $storedValue;

    protected $type;

    protected $modified = false;

    protected $loadedFromDb = false;

    protected $deleted = false;

    protected $checksum;

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
            // strlen('CustomVariable') === 14
            $this->type = substr($class, 14);
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

    public function toJson()
    {
        if ($this->getDbFormat() === 'string') {
            return json_encode($this->getDbValue());
        } else {
            return $this->getDbValue();
        }
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

    /**
     * @param $value
     * @return $this
     */
    abstract public function setValue($value);

    abstract public function getValue();

    /**
     * @param bool $renderExpressions
     * @return string
     */
    public function toConfigString($renderExpressions = false)
    {
        // TODO: this should be an abstract method once we deprecate PHP < 5.3.9
        throw new LogicException(sprintf(
            '%s has no toConfigString() implementation',
            get_class($this)
        ));
    }

    public function flatten(array &$flat, $prefix)
    {
        $flat[$prefix] = $this->getDbValue();
    }

    public function render($renderExpressions = false)
    {
        return c::renderKeyValue(
            $this->renderKeyName($this->getKey()),
            $this->toConfigStringPrefetchable($renderExpressions)
        );
    }

    protected function renderKeyName($key)
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/i', $key)) {
            return 'vars.' . c::escapeIfReserved($key);
        } else {
            return 'vars[' . c::renderString($key) . ']';
        }
    }

    public function checksum()
    {
        // TODO: remember checksum, invalidate on change
        return sha1($this->getKey() . '=' . $this->toJson(), true);
    }

    public function isNew()
    {
        return ! $this->loadedFromDb;
    }

    public function hasBeenModified()
    {
        return $this->modified;
    }

    public function toConfigStringPrefetchable($renderExpressions = false)
    {
        if (PrefetchCache::shouldBeUsed()) {
            return PrefetchCache::instance()->renderVar($this, $renderExpressions);
        } else {
            return $this->toConfigString($renderExpressions);
        }
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

    protected function setChecksum($checksum)
    {
        $this->checksum = $checksum;
        return $this;
    }

    public function getChecksum()
    {
        return $this->checksum;
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
            throw new LogicException(sprintf('WTF (%s): %s', $key, var_export($value, true)));
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
                throw new InvalidArgumentException(
                    'Icinga code expressions are not yet supported'
                );
            default:
                throw new InvalidArgumentException(sprintf(
                    '%s is not a supported custom variable format',
                    $row->format
                ));
        }
        if (property_exists($row, 'checksum')) {
            $var->setChecksum($row->checksum);
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
