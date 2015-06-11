<?php

namespace Icinga\Module\Director\CustomVariable;

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

        if ($value === null) {
            unset($this->$key);
        }

        if ($value === $this->get($key)) {
            return $this;
        }

        $this->vars[$key] = $value;
        $this->modified = true;

        return $this;
    }

    public function hasBeenModified()
    {
        return $this->modifiec;
    }

    public function setUnmodified()
    {
        $this->modified = false;
        $this->storedVars = $this->vars;
        return $this;
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
        return array_key_exists($key, $this->properties);
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


}
