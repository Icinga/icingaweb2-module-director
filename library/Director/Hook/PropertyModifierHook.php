<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Db;

abstract class PropertyModifierHook
{
    /** @var array */
    protected $settings = [];

    /** @var string */
    private $targetProperty;

    /** @var Db */
    private $db;

    /** @var bool */
    private $rejected = false;

    /** @var \stdClass */
    private $row;

    /**
     * Methode to transform the given value
     *
     * Your custom property modifier needs to implement this method.
     *
     * @return mixed $value
     */
    abstract public function transform($value);

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

    /**
     * Whether this PropertyModifier wants to deal with array on it's own
     *
     * When true, the whole array value will be passed to transform(), otherwise
     * transform() will be called for every single array member
     *
     * @return bool
     */
    public function hasArraySupport()
    {
        return false;
    }

    /**
     * Reject this whole row
     *
     * Allows your property modifier to reject specific rows
     *
     * @param bool $reject
     * @return $this
     */
    public function rejectRow($reject = true)
    {
        $this->rejected = (bool) $reject;

        return $this;
    }

    /**
     * Whether this PropertyModifier wants access to the current row
     *
     * When true, the your modifier can access the current row via $this->getRow()
     *
     * @return bool
     */
    public function requiresRow()
    {
        return false;
    }

    /**
     * Whether this modifier wants to reject the current row
     *
     * @return bool
     */
    public function rejectsRow()
    {
        return $this->rejected;
    }

    /**
     * Get the current row
     *
     * Will be null when requiresRow was not null. Please do not modify the
     * row. It might work right now, as we pass in an object reference for
     * performance reasons. However, modifying row properties is not supported,
     * and the outcome of such operation might change without pre-announcement
     * in any future version.
     *
     * @return \stdClass|null
     */
    public function getRow()
    {
        return $this->row;
    }

    /**
     * Sets the current row
     *
     * Please see requiresRow/getRow for related details. This method is called
     * by the Import implementation, you should never need to call this on your
     * own - apart from writing tests of course.
     *
     * @param \stdClass $row
     * @return $this
     */
    public function setRow($row)
    {
        $this->row = $row;
        return $this;
    }

    /**
     * The desired target property. Modifiers might want to have their outcome
     * written to another property of the current row.
     *
     * @param $property
     * @return $this
     */
    public function setTargetProperty($property)
    {
        $this->targetProperty = $property;
        return $this;
    }

    /**
     * Whether the result of transform() should be written to a new property
     *
     * The Import implementation deals with this
     *
     * @return bool
     */
    public function hasTargetProperty()
    {
        return $this->targetProperty !== null;
    }

    /**
     * Get the configured target property
     *
     * @return string
     */
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

    public function exportSettings()
    {
        return (object) $this->settings;
    }

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
