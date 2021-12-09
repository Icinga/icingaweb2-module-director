<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierRegexReplace extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'pattern', array(
            'label'       => 'Regex pattern',
            'description' => $form->translate(
                'The pattern you want to search for. This can be a regular expression like /^www\d+\./'
            ),
            'required'    => true,
        ));

        $form->addElement('text', 'replacement', array(
            'label'       => 'Replacement',
            'description' => $form->translate(
                'The string that should be used as a preplacement'
            ),
        ));
    }

    public function getName()
    {
        return 'Regular expression based replacement';
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        return preg_replace(
            $this->getSetting('pattern'),
            $this->getSetting('replacement'),
            $value
        );
    }
}
