<?php

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Db;
use Icinga\Exception\ConfigurationError;

abstract class ImportSourceHook
{
    protected $settings = [];

    public function getName()
    {
        $parts = explode('\\', get_class($this));
        $class = preg_replace('/ImportSource/', '', array_pop($parts));

        if (array_shift($parts) === 'Icinga' && array_shift($parts) === 'Module') {
            $module = array_shift($parts);
            if ($module !== 'Director') {
                if ($class === '') {
                    return sprintf('%s module', $module);
                }
                return sprintf('%s (%s)', $class, $module);
            }
        }

        return $class;
    }

    public static function forImportSource(ImportSource $source)
    {
        $db = $source->getDb();
        $settings = $db->fetchPairs(
            $db->select()->from(
                'import_source_setting',
                ['setting_name', 'setting_value']
            )->where('source_id = ?', $source->get('id'))
        );

        $className = $source->get('provider_class');
        if (! class_exists($className)) {
            throw new ConfigurationError(
                'Cannot load import provider class %s',
                $className
            );
        }

        /** @var ImportSourceHook $obj */
        $obj = new $className();
        $obj->setSettings($settings);
        return $obj;
    }

    public static function loadByName($name, Db $db)
    {
        $db = $db->getDbAdapter();
        $source = $db->fetchRow(
            $db->select()->from(
                'import_source',
                array('id', 'provider_class')
            )->where('source_name = ?', $name)
        );

        $settings = $db->fetchPairs(
            $db->select()->from(
                'import_source_setting',
                array('setting_name', 'setting_value')
            )->where('source_id = ?', $source->id)
        );

        if (! class_exists($source->provider_class)) {
            throw new ConfigurationError(
                'Cannot load import provider class %s',
                $source->provider_class
            );
        }
        /** @var ImportSourceHook $obj */
        $obj = new $source->provider_class();
        $obj->setSettings($settings);

        return $obj;
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
     * Returns an array containing importable objects
     *
     * @return array
     */
    abstract public function fetchData();

    /**
     * Returns a list of all available columns
     *
     * @return array
     */
    abstract public function listColumns();

    /**
     * Override this method in case you want to suggest a default
     * key column
     *
     * @return string|null Default key column
     */
    public static function getDefaultKeyColumnName()
    {
        return null;
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
