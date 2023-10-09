<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierRegexReplace extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'pattern', [
            'label'       => $form->translate('Regex pattern'),
            'description' => $form->translate(
                'The pattern you want to search for. This can be a regular expression like /^www\d+\./'
            ),
            'required'    => true,
        ]);

        $form->addElement('text', 'replacement', [
            'label'       => $form->translate('Replacement'),
            'description' => $form->translate(
                'The string that should be used as a replacement'
            ),
        ]);
        $form->addElement('select', 'when_not_matched', [
            'label'       => $form->translate('When not matched'),
            'description' => $form->translate(
                "What should happen, if the given pattern doesn't match"
            ),
            'value' => 'keep',
            'multiOptions' => [
                'keep'     => $form->translate('Keep the given string'),
                'set_null' => $form->translate('Set the value to NULL')
            ]
        ]);
    }

    public function getName()
    {
        return mt('director', 'Regular expression based replacement');
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        $result = preg_replace($this->getSetting('pattern'), $this->getSetting('replacement'), $value);
        if ($result === $value && $this->getSetting('when_not_matched', 'keep') === 'set_null') {
            if (!preg_match($this->getSetting('pattern'), $value)) {
                return null;
            }
        }

        return $result;
    }
}
