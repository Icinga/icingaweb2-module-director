<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierSplit extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'delimiter', array(
            'label'       => $form->translate('Delimiter'),
            'required'    => true,
            'description' => $form->translate(
                'One or more characters that should be used to split this string'
            )
        ));
    }

    public function transform($value)
    {
        return preg_split(
            '/' . preg_quote($this->getSetting('delimiter'), '/') . '/',
            $value,
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    }
}
