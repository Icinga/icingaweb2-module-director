<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use InvalidArgumentException;

class PropertyModifierTrim extends PropertyModifierHook
{
    const VALID_METHODS = ['trim', 'ltrim', 'rtrim'];

    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('select', 'trim_method', [
            'label'       => $form->translate('Trim Method'),
            'description' => $form->translate('Where to trim the string(s)'),
            'value'       => 'trim',
            'multiOptions' =>  $form->optionalEnum([
                'trim'  => $form->translate('Beginning and Ending'),
                'ltrim' => $form->translate('Beginning only'),
                'rtrim' => $form->translate('Ending only'),
            ]),
            'required' => true,
        ]);

        $form->addElement('text', 'character_mask', [
            'label' => $form->translate('Character Mask'),
            'description' => $form->translate(
                'Specify the characters that trim should remove.'
                . 'Default is: " \t\n\r\0\x0B"'
            ),
        ]);
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        $mask = $this->getSetting('character_mask');
        $method = $this->getSetting('trim_method');
        if (in_array($method, self::VALID_METHODS)) {
            if ($mask) {
                return $method($value, $mask);
            } else {
                return $method($value);
            }
        }

        throw new InvalidArgumentException("'$method' is not a valid trim method");
    }
}
