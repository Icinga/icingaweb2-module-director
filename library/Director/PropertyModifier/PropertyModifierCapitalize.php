<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierCapitalize extends PropertyModifierHook
{

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'lowerfirst', array(
            'label'       => 'Use lowercase first',
            'required'    => true,
            'description' => $form->translate(
                'This modifier capitalizes strings, should he lowercase first?'
            ),
            'value' => 'true',
            'multiOptions' => $form->optionalEnum(array(
                'y'  => $form->translate('Yes'),
                'n' => $form->translate('No'),
            )),
        ));
    }


    public function transform($value)
    {
        if ($this->getSetting('lowerfirst') === 'y') {
            return ucwords(strtolower($value));
        } else {
            return ucwords($value);
        }
    }
}
