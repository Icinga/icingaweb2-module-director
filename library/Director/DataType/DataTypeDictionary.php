<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class DataTypeDictionary extends DataTypeHook
{
    protected $defaultValue = null;
    protected $fieldSettingsMap = [];
    protected $dbAdapter = null;

    public function getFormElement($name, QuickForm $form)
    {
        $this->dbAdapter = $form->getDb()->getDbAdapter();
        $this->initDefaultValueAndSettingsMap($this->getSetting('reference_id'));

        $element = $form->createElement('dictionary', $name, array(
            'label'       => 'DB Query',
            'rows'        => 5,
        ));

        $element->setDefaultValue($this->defaultValue);
        $element->setFieldSettingsMap($this->fieldSettingsMap);

        return $element;
    }

    public static function getFormat()
    {
        return 'json';
    }

    protected function initDefaultValueAndSettingsMap($dictionary_id, $keyPrefix = '') {
        $this->defaultValue = $this->initDefaultValueAndSettingsMapRec($dictionary_id, $keyPrefix);
    }

    protected function initDefaultValueAndSettingsMapRec($dictionary_id, $keyPrefix)
    {
        $result = array();
        $select = $this->dbAdapter->select()
            ->from(array('df' => 'director_dictionary_field'),
                array(
                    'varname' => 'df.varname',
                    'datatype' => 'df.datatype',
                    'is_required' => 'df.is_required',
                    'setting_name' => 'dfs.setting_name',
                    'setting_value' => 'dfs.setting_value'
                ))
            ->where('df.dictionary_id = ?', $dictionary_id)
            ->joinLeft(
                array('dfs' => 'director_dictionary_field_setting'),
                'dfs.dictionary_field_id = df.id',
                array()
            )
            ->order('varname ASC');


        foreach ($this->dbAdapter->fetchAll($select) as $field) {
            $fullKey = $keyPrefix . $field->varname;
            $this->fieldSettingsMap[$fullKey] = [
                'is_required' => $field->is_required === 'y'
            ];
            $result[$field->varname] = $this->getDefaultValueForField($field, $fullKey);
        }
        return $result;
    }

    protected function getDefaultValueForField($field, $fullKey) {
        switch ($field->datatype) {
            case 'Icinga\Module\Director\DataType\DataTypeArray':
                return [];
            case 'Icinga\Module\Director\DataType\DataTypeString':
                return "";
            case 'Icinga\Module\Director\DataType\DataTypeNumber':
                return 0;
            case 'Icinga\Module\Director\DataType\DataTypeDictionary':
                if ($field->setting_name === 'reference_id' && $field->setting_value) {
                    return $this->initDefaultValueAndSettingsMapRec($field->setting_value, $fullKey . '.');
                }
                return null;
            default:
                return null;
        }
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $db = $form->getDb();

        $form->addElement('select', 'reference_id', array(
            'label'    => 'Dictionary name',
            'required' => true,
            'multiOptions' => array(null => '- please choose -') +
                $db->enumDictionary(),
        ));
        return $form;
    }
}
