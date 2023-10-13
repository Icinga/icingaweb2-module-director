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

        $form->addElement('select', 'when_empty', array(
            'label'       => $form->translate('When empty'),
            'required'    => true,
            'description' => $form->translate(
                'What should happen when the given string is empty?'
            ),
            'value'        => 'empty_array',
            'multiOptions' => $form->optionalEnum(array(
                'empty_array' => $form->translate('return an empty array'),
                'null'        => $form->translate('return NULL'),
            ))
        ));
    }

    public function transform($value)
    {
        if ($value === null || ! strlen(trim($value))) {
            if ($this->getSetting('when_empty', 'empty_array') === 'empty_array') {
                return array();
            } else {
                return null;
            }
        }

        return preg_split(
            '/' . preg_quote($this->getSetting('delimiter'), '/') . '/',
            $value,
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    }
}
