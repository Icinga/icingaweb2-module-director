<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Web\Hook\PropertyModifierHook;

class PropertyModifierSubstring extends PropertyModifierHook
{

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'pattern', array(
            'label'    => 'Regex pattern',
            'required' => true,
        ));
        $form->addElement('text', 'replacement', array(
            'label'    => 'Replacement',
            'required' => true,
        ));
        return $form;
    }

    public function transform($value)
    {
        return substr($value, $this->settings['start'], $this->settings['end'] - $this->settings['start']);
    }

}
