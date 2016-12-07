<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierRegexpSplit extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'pattern', array(
            'label'       => $form->translate('Pattern'),
            'required'    => true,
            'description' => $form->translate(
                'Regular expression pattern to split the string (e.g. /\s+/ or /[,;]/)'
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
        if (! strlen(trim($value))) {
            if ($this->getSetting('when_empty', 'empty_array') === 'empty_array') {
                return array();
            } else {
                return null;
            }
        }

        return preg_split(
            $this->getSetting('pattern'),
            $value,
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    }
}
