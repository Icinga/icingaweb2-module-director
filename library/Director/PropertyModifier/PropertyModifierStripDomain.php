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
            'description' => $form->translate('The domain name you want to be stripped'),
            'required'    => true,
        ));
    }

    public function getName()
    {
        return 'Strip a domain name';
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        $domain = preg_quote(ltrim($this->getSetting('domain'), '.'), '/');

        return preg_replace(
            '/\.' . $domain . '$/',
            '',
            $value
        );
    }
}
