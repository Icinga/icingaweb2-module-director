<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Form\QuickForm;

class DataTypeDirectorObject extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        /** @var DirectorObjectForm $form */
        $db = $form->getDb()->getDbAdapter();

        $type = $this->getSetting('icinga_object_type');
        $dummy = IcingaObject::createByType($type);

        $display = in_array($type, ['service_set', 'notification'])
            ? 'object_name'
            : 'COALESCE(display_name, object_name)';
        $query = $db->select()->from($dummy->getTableName(), [
            'object_name'  => 'object_name',
            'display_name' => $display
        ])->order($display);

        if ($type === 'service_set') {
            $query->where('host_id IS NULL');
        } elseif ($type === 'notification') {
            $query->where('object_type = ?', 'apply');
        } else {
            $query->where('object_type = ?', 'object');
        }

        $enum = $db->fetchPairs($query);

        $params = [];
        if ($this->getSetting('data_type') === 'array') {
            $elementType = $type === 'notification' ? 'select' : 'extensibleSet';
            $params['sorted'] = true;
            $params = ['multiOptions' => $enum];
        } else {
            $params = ['multiOptions' => [
                    '' => $form->translate('- please choose -')
                ] + $enum];
            $elementType = 'select';
        }

        return $form->createElement($elementType, $name, $params);
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $enum = [
            'host'         => $form->translate('Hosts'),
            'hostgroup'    => $form->translate('Host groups'),
            'notification' => $form->translate('Notification Apply Rules'),
            'service'      => $form->translate('Services'),
            'servicegroup' => $form->translate('Service groups'),
            'service_set'  => $form->translate('Service Set'),
            'user'         => $form->translate('Users'),
            'usergroup'    => $form->translate('User groups'),
        ];

        $form->addElement('select', 'icinga_object_type', [
            'label'        => $form->translate('Object'),
            'description'  => $form->translate(
                'Please choose a specific Icinga object type'
            ),
            'required'     => true,
            'multiOptions' => $form->optionalEnum($enum),
            'sorted'       => true,
        ]);

        $form->addElement('select', 'data_type', [
            'label' => $form->translate('Target data type'),
            'multiOptions' => $form->optionalEnum([
                'string' => $form->translate('String'),
                'array'  => $form->translate('Array'),
            ]),
            'required' => true,
        ]);

        return $form;
    }
}
