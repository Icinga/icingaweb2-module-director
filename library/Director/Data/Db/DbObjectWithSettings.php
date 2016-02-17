<?php

namespace Icinga\Module\Director\Data\Db;

abstract class DbObjectWithSettings extends DbObject
{
    protected $settingsTable = 'your_table_name';

    protected $settingsRemoteId = 'column_pointing_to_main_table_id';

    protected $settings = array();

    public function set($key, $value)
    {
        if ($this->hasProperty($key)) {
            return parent::set($key, $value);
        }

        if (! array_key_exists($key, $this->settings) || $value !== $this->settings[$key]) {
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

    public function getSettings()
    {
        return $this->settings;
    }

    public function getSetting($name, $default = null)
    {
        if (array_key_exists($name, $this->settings)) {
            return $this->settings[$name];
        }

        return $default;
    }

    public function __unset($key)
    {
        if ($this->hasProperty($key)) {
            return parent::__set($key, $value);
        }

        if (array_key_exists($key, $this->settings)) {
            unset($this->settings[$key]);
            $this->hasBeenModified = true;
        }

        return $this;
    }

    protected function onStore()
    {
        $old = $this->fetchSettingsFromDb();
        $oldKeys = array_keys($old);
        $newKeys = array_keys($this->settings);
        $add = array();
        $mod = array();
        $del = array();

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

        $where = sprintf($this->settingsRemoteId . ' = %d AND setting_name = ?', $this->id);
        $db = $this->getDb();
        foreach ($mod as $key => $val) {
            $db->update(
                $this->settingsTable,
                array('setting_value' => $val),
                $db->quoteInto($where, $key)
            );
        }

        foreach ($add as $key => $val) {
            $db->insert(
                $this->settingsTable,
                array(
                    $this->settingsRemoteId => $this->id,
                    'setting_name'          => $key,
                    'setting_value'         => $val
                )
            );
        }

        if (! empty($del)) {
            $where = sprintf($this->settingsRemoteId . ' = %d AND setting_name IN (?)', $this->id);
            $db->delete($this->settingsTable, $db->quoteInto($where, $del));
        }
    }

    protected function fetchSettingsFromDb()
    {
        $db = $this->getDb();
        return $db->fetchPairs(
            $db->select()
               ->from($this->settingsTable, array('setting_name', 'setting_value'))
               ->where($this->settingsRemoteId . ' = ?', $this->id)
        );

    }

    protected function onLoadFromDb()
    {
        $this->settings = $this->fetchSettingsFromDb();
    }
}
