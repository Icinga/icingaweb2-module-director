<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Db;

abstract class PropertyModifierHook
{
    protected $settings = array();

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

    public static function loadById($property_id, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $modifier = $db->fetchRow(
            $db->select()->from(
                'import_row_modifier',
                array('id', 'provider_class')
            )->where('property_id = ?', $property_id)
        );

        $settings = $db->fetchPairs(
            $db->select()->from(
                'import_row_modifier_settings',
                array('setting_name', 'setting_value')
            )->where('modifier_id = ?', $modifier->id)
        );

        $obj = new $modifier->provider_class;
        $obj->setSettings($settings);
        $obj->setDb($db);

        return $obj;
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
