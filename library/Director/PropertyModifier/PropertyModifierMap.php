<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Import\SyncUtils;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierMap extends PropertyModifierHook
{
    private $cache;

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'datalist_id', array(
            'label'       => 'Lookup list',
            'required'    => true,
            'description' => $form->translate(
                'Please choose a data list that can be used for map lookups'
            ),
            'multiOptions' => $form->optionalEnum($form->getDb()->enumDatalist()),
        ));

        $form->addElement('select', 'on_missing', array(
            'label'       => 'Missing entries',
            'required'    => true,
            'description' => $form->translate(
                'What should happen if the lookup key does not exist in the data list?'
                . ' You could return a null value, keep the unmodified imported value'
                . ' or interrupt the import process'
            ),
            'multiOptions' => $form->optionalEnum(array(
                'null'   => $form->translate('Set null'),
                'keep'   => $form->translate('Return lookup key unmodified'),
                'custom' => $form->translate('Return custom default value'),
                'fail'   => $form->translate('Let the import fail'),
            )),
            'class' => 'autosubmit',
        ));

        $method = $form->getSetting('on_missing');
        if ($method == 'custom') {
            $form->addElement('text', 'custom_value', array(
                'label'       => $form->translate('Default value'),
                'required'    => true,
                'description' => $form->translate(
                    'This value will be evaluated, and variables like ${some_column}'
                    . ' will be filled accordingly. A typical use-case is generating'
                    . ' unique service identifiers via ${host}!${service} in case your'
                    . ' data source doesn\'t allow you to ship such. The chosen "property"'
                    . ' has no effect here and will be ignored.'
                )
            ));
        }

        // TODO: ignore case
    }

    public function requiresRow()
    {
        return true;
    }

    public function transform($value)
    {
        $this->loadCache();
        if (array_key_exists($value, $this->cache)) {
            return $this->cache[$value];
        }

        switch ($this->getSetting('on_missing')) {
            case 'null':
                return null;

            case 'keep':
                return $value;

            case 'custom':
                return SyncUtils::fillVariables($this->getSetting('custom_value'), $this->getRow());

            case 'fail':
            default:
                throw new InvalidPropertyException(
                    '"%s" cannot be found in the "%s" data list',
                    $value,
                    $this->getDatalistName()
                );
        }
    }

    protected function getDatalistName()
    {
        $db = $this->getDb()->getDbAdapter();
        $query = $db->select()->from(
            'director_datalist',
            'list_name'
        )->where(
            'id = ?',
            $this->getSetting('datalist_id')
        );
        $result = $db->fetchOne($query);

        return $result;
    }

    protected function loadCache($force = false)
    {
        if ($this->cache === null || $force) {
            $this->cache = array();
            $db = $this->getDb()->getDbAdapter();
            $select = $db->select()->from(
                'director_datalist_entry',
                array('entry_name', 'entry_value')
            )->where('list_id = ?', $this->getSetting('datalist_id'))
            ->order('entry_value');

            $this->cache = $db->fetchPairs($select);
        }

        return $this;
    }
}
