<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\QuickForm;

class DataTypeDirectorObject extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        $db = $form->getDb()->getDbAdapter();

        $dummy = IcingaObject::createByType(
            $this->getSetting('icinga_object_type')
        );

        $query = $db->select()->from($dummy->getTableName(), array(
            'object_name'  => 'object_name',
            'display_name' => 'COALESCE(display_name, object_name)'
        ))->where(
            'object_type = ?',
            'object'
        );

        $enum = $db->fetchPairs($query);

        $params = array(
            'multiOptions' => array(
                null => $form->translate('- please choose -'),
            ) + $enum,
        );

        if ($this->getSetting('data_type') === 'array') {
            $type = 'extensibleSet';
            $params['sorted'] = true;
        } else {
            $type = 'select';
        }

        return $form->createElement($type, $name, $params);
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $enum = array(
            'host'         => $form->translate('Hosts'),
            'hostgroup'    => $form->translate('Host groups'),
            'service'      => $form->translate('Services'),
            'servicegroup' => $form->translate('Service groups'),
            'user'         => $form->translate('Users'),
            'usergroup'    => $form->translate('User groups'),
        );

        $form->addElement('select', 'icinga_object_type', array(
            'label'        => $form->translate('Object'),
            'description'  => $form->translate(
                'Please choose a specific Icinga object type'
            ),
            'required'     => true,
            'multiOptions' => $form->optionalEnum($enum),
            'sorted'       => true,
        ));

        $form->addElement('select', 'data_type', array(
            'label' => $form->translate('Target data type'),
            'multiOptions' => $form->optionalEnum(array(
                'string' => $form->translate('String'),
                'array' => $form->translate('Array'),
            )),
            'required' => true,
        ));

        return $form;
    }
}
