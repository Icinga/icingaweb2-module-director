<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Web\Hook\PropertyModifierHook;

class PropertyModifierStripDomain extends PropertyModifierHook
{

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'domain', array(
            'label'       => 'Domain name',
	    'description' => 'Domain to be replaced',
            'required'    => true,
        ));
        return $form;
    }

    public function transform($value)
    {
        return preg_replace($this->settings['domain'], "", $value);
    }

}
