<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Web\Form\QuickForm;

abstract class DataTypeHook
{
    protected $settings = array();

    public function getName()
    {
        $parts = explode('\\', get_class($this));
        $class = preg_replace('/DataType/', '', array_pop($parts));

        if (array_shift($parts) === 'Icinga' && array_shift($parts) === 'Module') {
            $module = array_shift($parts);
            if ($module !== 'Director') {
                return sprintf('%s (%s)', $class, $module);
            }
        }

        return $class;
    }

    public static function getFormat()
    {
        return 'string';
    }

    abstract public function getFormElement($name, QuickForm $form);

    public static function addSettingsFormFields(QuickForm $form)
    {
        return $form;
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
}
