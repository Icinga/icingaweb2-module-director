<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierStripDomain extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'domain', array(
            'label'       => 'Domain name',
            'description' => 'Domain to be replaced',
            'required'    => true,
        ));
    }

    public function transform($value)
    {
        return preg_replace($this->settings['domain'], '', $value);
    }
}
