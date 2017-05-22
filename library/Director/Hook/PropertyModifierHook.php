<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Db;

abstract class PropertyModifierHook
{
    protected $settings = array();

    private $targetProperty;

    private $db;

    public function getName()
    {
        $parts = explode('\\', get_class($this));
        $class = preg_replace('/^PropertyModifier/', '', array_pop($parts)); // right?

        if (array_shift($parts) === 'Icinga' && array_shift($parts) === 'Module') {
            $module = array_shift($parts);
            if ($module !== 'Director') {
                return sprintf('%s (%s)', $class, $module);
            }
        }

        return $class;
    }

    public function hasArraySupport()
    {
        return false;
    }

    public function setTargetProperty($property)
    {
        $this->targetProperty = $property;
        return $this;
    }

    public function hasTargetProperty()
    {
        return $this->targetProperty !== null;
    }

    public function getTargetProperty($default = null)
    {
        if ($this->targetProperty === null) {
            return $default;
        }

        return $this->targetProperty;
    }

    public function setDb(Db $db)
    {
        $this->db = $db;
        return $this;
    }

    public function getDb()
    {
        return $this->db;
    }

    public function setSettings($settings)
    {
        $this->settings = $settings;
        return $this;
    }

    public function getSetting($name, $default = null)
    {
        if (array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        } else {
            return $default;
        }
    }

    public function setSetting($name, $value)
    {
        $this->settings[$name] = $value;
        return $this;
    }

    /**
     * Methode to transform the given value
     *
     * @return value
     */
    abstract public function transform($value);

    /**
     * Override this method if you want to extend the settings form
     *
     * @param  QuickForm $form QuickForm that should be extended
     * @return QuickForm
     */
    public static function addSettingsFormFields(QuickForm $form)
    {
        return $form;
    }
}
