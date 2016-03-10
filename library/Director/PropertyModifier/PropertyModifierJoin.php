<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierJoin extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'glue', array(
            'label'       => $form->translate('Glue'),
            'required'    => false,
            'description' => $form->translate(
                'One or more characters that will be used to glue an input array to a string. Can be left empty'
            )
        ));
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        return implode($this->getSetting('glue'), $value);
    }
}
