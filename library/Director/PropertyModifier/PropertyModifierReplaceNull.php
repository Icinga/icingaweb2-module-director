<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierReplaceNull extends PropertyModifierHook
{
    public function getName()
    {
        return 'Replace null value with String';
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'string', [
            'label'       => 'Replacement String',
            'description' => $form->translate('Your replacement string'),
            'required'    => true,
        ]);
    }

    public function transform($value)
    {
        if ($value === null) {
            return $this->getSetting('string');
        } else {
            return $value;
        }
    }
}
