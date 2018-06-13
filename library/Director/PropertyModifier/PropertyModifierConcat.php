<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierConcat extends PropertyModifierHook
{
    public static function addSettingsFormFields(QuickForm $form)
    {
        $form->addElement('text', 'before', array(
            'label'       => 'Before',
            'description' => sprintf(
                $form->translate(
                    'The Property is concatenated: $before.$propertyvalue.$start - See %s for details.'
                ),
                'http://php.net/manual/de/internals2.opcodes.concat.php'
            )
        ));

        $form->addElement('text', 'after', array(
            'label'    => 'After',
            'description' => sprintf(
                $form->translate(
                    'The Property is concatenated: $before.$propertyvalue.$start - See %s for details.'
                ),
                'http://php.net/manual/de/internals2.opcodes.concat.php'
            )
        ));
    }

    public function transform($value)
    {
        $before = $this->getSetting('before');
        $after = $this->getSetting('after');
        return $before.$value.$after;
    }
}
