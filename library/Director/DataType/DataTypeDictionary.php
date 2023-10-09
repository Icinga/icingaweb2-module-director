<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Form\QuickForm;
use InvalidArgumentException;
use ipl\Html\Html;
use RuntimeException;

class DataTypeDictionary extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        if (strpos($name, 'var_') !== 0) {
            throw new InvalidArgumentException(
                "'$name' is not a valid candidate for a Nested Dictionary, 'var_*' expected"
            );
        }
        /** @var DirectorObjectForm $form */
        $object = $form->getObject();
        if ($form->isTemplate()) {
            return $form->createElement('simpleNote', $name, [
                'ignore' => true,
                'value' => Html::tag('span', $form->translate('To be managed on objects only')),
            ]);
        }
        if (! $object->hasBeenLoadedFromDb()) {
            return $form->createElement('simpleNote', $name, [
                'ignore' => true,
                'value' => Html::tag(
                    'span',
                    $form->translate('Can be managed once this object has been created')
                ),
            ]);
        }
        $params = [
            'varname' => substr($name, 4),
        ];
        if ($object instanceof IcingaHost) {
            $params['host'] = $object->getObjectName();
        } elseif ($object instanceof IcingaService) {
            $params['host'] = $object->get('host');
            $params['service'] = $object->getObjectName();
        }
        return $form->createElement('InstanceSummary', $name, [
            'linkParams' => $params
        ]);
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        /** @var DirectorObjectForm $form */
        $db = $form->getDb()->getDbAdapter();
        $enum = [
            'host' => $form->translate('Hosts'),
            'service' => $form->translate('Services'),
        ];

        $form->addElement('select', 'template_object_type', [
            'label' => $form->translate('Template (Object) Type'),
            'description' => $form->translate(
                'Please choose a specific Icinga object type'
            ),
            'class'    => 'autosubmit',
            'required' => true,
            'multiOptions' => $form->optionalEnum($enum),
            'sorted' => true,
        ]);

        // There should be a helper method for this
        if ($form->hasBeenSent()) {
            $type = $form->getSentOrObjectValue('template_object_type');
        } else {
            $type = $form->getObject()->getSetting('template_object_type');
        }
        if (empty($type)) {
            return $form;
        }

        if (array_key_exists($type, $enum)) {
            $form->addElement('select', 'template_name', [
                'label' => $form->translate('Template'),
                'multiOptions' => $form->optionalEnum(self::fetchTemplateNames($db, $type)),
                'required' => true,
            ]);
        } else {
            throw new RuntimeException("$type is not a valid Dictionary object type");
        }

        return $form;
    }

    protected static function fetchTemplateNames($db, $type)
    {
        $query = $db->select()
            ->from("icinga_$type", ['a' => 'object_name', 'b' => 'object_name'])
            ->where('object_type = ?', 'template')
            ->where('template_choice_id IS NULL')
            ->order('object_name');

        return $db->fetchPairs($query);
    }
}
