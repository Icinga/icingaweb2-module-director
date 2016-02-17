<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierReplace PropertyModifierHook
{

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'string', array(
            'label'    => 'Search string',
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
        return str_replace($this->settings['string'], $this->settings['replacement'], $value);
    }

}
