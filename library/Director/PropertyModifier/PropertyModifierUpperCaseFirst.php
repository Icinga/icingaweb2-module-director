<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierUpperCaseFirst extends PropertyModifierHook
{
    public function getName()
    {
        return 'Uppercase the first character of each word in a string';
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'lowerfirst', array(
            'label'       => $form->translate('Use lowercase first'),
            'required'    => true,
            'description' => $form->translate(
                'Should all the other characters be lowercased first?'
            ),
            'value' => 'y',
            'multiOptions' => array(
                'y' => $form->translate('Yes'),
                'n' => $form->translate('No'),
            ),
        ));
    }


    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        if ($this->getSetting('lowerfirst', 'y') === 'y') {
            return ucwords(strtolower($value));
        } else {
            return ucwords($value);
        }
    }
}
