<?php

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class CustomVariables
{
    protected $storedVars = array();

    protected $vars = array();

    protected $modified = false;

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

        if (! $value instanceof CustomVariable) {
            $value = CustomVariable::create($key, $value);
        }

        if (isset($this->$key) && $value->equals($this->get($key))) {
            return $this;
        }

        $this->vars[$key] = $value;
        $this->modified = true;

        return $this;
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
        return $this->modified;
    }

    public function setUnmodified()
    {
        $this->modified = false;
        $this->storedVars = $this->vars;
        return $this;
    }   

    public function toConfigString()
    {
        $out = '';

        foreach ($this->vars as $key => $var) {
            $out .= c::renderKeyValue(
                c::escapeIfReserved($key),
                $var->toConfigString(),
                '    vars.'
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
        if (! array_key_exists($key, $this->properties)) {
            throw new Exception('Trying to unset invalid key');
        }
        $this->properties[$key] = $this->defaultProperties[$key];
    }

    public function __toString()
    {
        try {
            return $this->toConfigString();
        } catch (Exception $e) {
            trigger_error($e);
            $previousHandler = set_exception_handler(function () {});
            restore_error_handler();
            call_user_func($previousHandler, $e);
            die();
        }
    }
}
