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
        ));
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        return str_replace(
            $this->getSetting('string'),
            $this->getSetting('replacement'),
            $value
        );
    }
}
