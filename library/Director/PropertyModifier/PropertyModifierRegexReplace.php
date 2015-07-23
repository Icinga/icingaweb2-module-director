<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Web\Hook\PropertyModifierHook;

class PropertyModifierRegexReplace extends PropertyModifierHook
{


    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'start', array(
            'label'    => 'Start index',
            'required' => true,
        ));
        $form->addElement('text', 'start', array(
            'label'    => 'End index',
            'required' => true,
        ));
        return $form;
    }


    public function transform($value)
    {
        return preg_replace($this->settings['pattern'], $this->settings['replacement'], $value);
    }

}
