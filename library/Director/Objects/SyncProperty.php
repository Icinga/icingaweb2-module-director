<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Data\Db\DbObject;

class SyncProperty extends DbObject
{
    protected $table = 'sync_property';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'             	=> null,
        'rule_id'        	=> null,
        'source_id' 		=> null,
        'source_expression' => null,
	    'destination_field'	=> null,
	    'priority'		    => null,
	    'filter_expression'	=> null,
    	'merge_policy'		=> null
    );

    public function setSource_column($value)
    {
        $this->source_expression = '$(' . $value . ')';
        return $this; 
    }

    /*
    protected $properties = array();

    public function set($key, $value)
    {
        if ($this->hasProperty($key)) {
            return parent::set($key, $value);
        }

        if (! array_key_exists($key, $this->propterties) || $value !== $this->propterties[$key]) {
            $this->hasBeenModified = true;
        }
        $this->properties[$key] = $value;
        return $this;
    }

    public function get($key)
    {
        if ($this->hasProperty($key)) {
            return parent::get($key);
        }

        if (array_key_exists($key, $this->properties)) {
            return $this->properties[$key];
        }

        return parent::get($key);
    }

    public function getProperties()
    {
        return $this->properties;
    }

    protected function onStore()
    {
        $old = $this->fetchSettingsFromDb();
        $oldKeys = array_keys($old);
        $newKeys = array_keys($this->properties);
        $add = array();
        $mod = array();
        $del = array();

        foreach ($this->properties as $key => $val) {
            if (array_key_exists($key, $old)) {
                if ($old[$key] !== $this->properties[$key]) {
                    $mod[$key] = $this->properties[$key];
                }
            } else {
                $add[$key] = $this->properties[$key];
            }
        }

        foreach (array_diff(array_keys($old), array_keys($this->settings)) as $key) {
            $del[$key] = $key;
        }

	$modifier = $db->fetchRow(
            $db->select()->from(
                'sync_modifier',
                array('id')
            )->where('property_id = ?', $property_id)
        )	

        $where = sprintf('modifier_id = %d AND param_key = ?', $modifier->id);
        $db = $this->getDb();
        foreach ($mod as $key => $val) {
            $db->update(
                'sync_modifier_param',
                array('param_value' => $val),
                $db->quoteInto($where, $key)
            );
        }

        foreach ($add as $key => $val) {
	    $db->insert(
		'sync_modifier',
		array(
		    'property_id' => $this->id,
		    '
		)
            $db->insert(
                'sync_modifier_param',
                array(
                    'source_id'     => $this->id,
                    'setting_name'  => $key,
                    'setting_value' => $val
                )
            );
        }

        foreach ($del as $key) {
            $db->update(
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
               ->from('sync_modifier_param', array('param_name', 'param_value'))
               ->where('modifier_id = ?', $this->id)
        );

    }

    protected function onLoadFromDb()
    {
        $this->settings = $this->fetchSettingsFromDb();
    } */
}
