<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierReplace extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'string', array(
            'label'       => 'Search string',
            'description' => $form->translate('The string you want to search for'),
            'required'    => true,
        ));

        $form->addElement('text', 'replacement', array(
            'label'       => 'Replacement',
            'description' => $form->translate('Your replacement string'),
            'required'    => true,
        ));
    }

    public function transform($value)
    {
        return str_replace($this->settings['string'], $this->settings['replacement'], $value);
    }
}
