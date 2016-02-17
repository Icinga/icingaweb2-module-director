<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierRegexReplace extends PropertyModifierHook
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
        return preg_replace($this->settings['pattern'], $this->settings['replacement'], $value);
    }

}
