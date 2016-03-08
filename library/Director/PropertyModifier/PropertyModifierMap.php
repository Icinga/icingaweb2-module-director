<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
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
                'null' => $form->translate('Set null'),
                'keep' => $form->translate('Return lookup key unmodified'),
                'fail' => $form->translate('Let the import fail'),
            )),
        ));

        // TODO: ignore case
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
        // TODO: need the db for ->enumDatalist()
        return sprintf('List with id %s', $this->getSetting('datalist'));
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
