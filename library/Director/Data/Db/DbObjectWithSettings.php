<?php

namespace Icinga\Module\Director\Data\Db;

use Icinga\Module\Director\Db;

abstract class DbObjectWithSettings extends DbObject
{
    /** @var Db $connection */
    protected $connection;

    protected $settingsTable = 'your_table_name';

    protected $settingsRemoteId = 'column_pointing_to_main_table_id';

    protected $settings = [];

    public function set($key, $value)
    {
        if ($this->hasProperty($key)) {
            return parent::set($key, $value);
        } elseif ($this->hasSetterForProperty($key)) { // Hint: hasProperty checks only for Getters
            return parent::set($key, $value);
        }

        if (! \array_key_exists($key, $this->settings) || $value !== $this->settings[$key]) {
            $this->hasBeenModified = true;
        }

        $this->settings[$key] = $value;
        return $this;
    }

    public function get($key)
    {
        if ($this->hasProperty($key)) {
            return parent::get($key);
        }

        if (array_key_exists($key, $this->settings)) {
            return $this->settings[$key];
        }

        return parent::get($key);
    }

    public function setSettings($settings)
    {
        $settings = (array) $settings;
        ksort($settings);
        if ($settings !== $this->settings) {
            $this->settings = $settings;
            $this->hasBeenModified = true;
        }

        return $this;
    }

    public function getSettings()
    {
        // Sort them, important only for new objects
        ksort($this->settings);
        return $this->settings;
    }

    public function getSetting($name, $default = null)
    {
        if (array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        }

        return $default;
    }

    public function getStoredSetting($name, $default = null)
    {
        $stored = $this->fetchSettingsFromDb();
        if (array_key_exists($name, $stored)) {
            return $stored[$name];
        }

        return $default;
    }

    public function __unset($key)
    {
        if ($this->hasProperty($key)) {
            parent::__unset($key);
        }

        if (array_key_exists($key, $this->settings)) {
            unset($this->settings[$key]);
            $this->hasBeenModified = true;
        }
    }

    protected function onStore()
    {
        $old = $this->fetchSettingsFromDb();
        $oldKeys = array_keys($old);
        $newKeys = array_keys($this->settings);
        $add = [];
        $mod = [];
        $del = [];
        $id = $this->get('id');

        foreach ($this->settings as $key => $val) {
            if (array_key_exists($key, $old)) {
                if ($old[$key] !== $this->settings[$key]) {
                    $mod[$key] = $this->settings[$key];
                }
            } else {
                $add[$key] = $this->settings[$key];
            }
        }

        foreach (array_diff($oldKeys, $newKeys) as $key) {
            $del[] = $key;
        }

        $where = sprintf($this->settingsRemoteId . ' = %d AND setting_name = ?', $id);
        $db = $this->getDb();
        foreach ($mod as $key => $val) {
            $db->update(
                $this->settingsTable,
                ['setting_value' => $val],
                $db->quoteInto($where, $key)
            );
        }

        foreach ($add as $key => $val) {
            $db->insert(
                $this->settingsTable,
                [
                    $this->settingsRemoteId => $id,
                    'setting_name'          => $key,
                    'setting_value'         => $val
                ]
            );
        }

        if (! empty($del)) {
            $where = sprintf($this->settingsRemoteId . ' = %d AND setting_name IN (?)', $id);
            $db->delete($this->settingsTable, $db->quoteInto($where, $del));
        }
    }

    protected function fetchSettingsFromDb()
    {
        $db = $this->getDb();
        $id = $this->get('id');
        if (! $id) {
            return [];
        }

        return $db->fetchPairs(
            $db->select()
               ->from($this->settingsTable, ['setting_name', 'setting_value'])
               ->where($this->settingsRemoteId . ' = ?', $id)
               ->order('setting_name')
        );
    }

    protected function onLoadFromDb()
    {
        $this->settings = $this->fetchSettingsFromDb();
    }
}
