<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierBitmask extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'bitmask', array(
            'label'       => 'Bitmask',
            'description' => $form->translate(
                'The numeric bitmask you want to apply. In case you have a hexadecimal'
                . ' or binary mask please transform it to a decimal number first. The'
                . ' result of this modifier is a boolean value, telling whether the'
                . ' given mask applies to the numeric value in your source column'
            ),
            'required'    => true,
        ));
    }

    public function getName()
    {
        return 'Bitmask match (numeric)';
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        $mask = (int) $this->getSetting('bitmask');
        return (((int) $value) & $mask) === $mask;
    }
}
