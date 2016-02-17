<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class ImportSource extends DbObject
{
    protected $table = 'import_source';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'             => null,
        'source_name'    => null,
        'provider_class' => null,
        'key_column'     => null
    );

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

        foreach (array_diff(array_keys($old), array_keys($this->settings)) as $key) {
            $del[$key] = $key;
        }

        $where = sprintf('source_id = %d AND setting_name = ?', $this->id);
        $db = $this->getDb();
        foreach ($mod as $key => $val) {
            $db->update(
                'import_source_setting',
                array('setting_value' => $val),
                $db->quoteInto($where, $key)
            );
        }

        foreach ($add as $key => $val) {
            $db->insert(
                'import_source_setting',
                array(
                    'source_id'     => $this->id,
                    'setting_name'  => $key,
                    'setting_value' => $val
                )
            );
        }

        foreach ($del as $key) {
            $db->delete(
                'import_source_setting',
                $db->quoteInto($where, $key)
            );
        }
    }

    protected function fetchSettingsFromDb()
    {
        $db = $this->getDb();
        return $db->fetchPairs(
            $db->select()
               ->from('import_source_setting', array('setting_name', 'setting_value'))
               ->where('source_id = ?', $this->id)
        );

    }

    protected function onLoadFromDb()
    {
        $this->settings = $this->fetchSettingsFromDb();
    }
}
