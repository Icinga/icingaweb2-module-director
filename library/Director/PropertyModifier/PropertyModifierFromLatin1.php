<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class PropertyModifierFromLatin1 extends PropertyModifierHook
{
    public function getName()
    {
        return 'Convert a latin1 string to utf8';
    }

    public function transform($value)
    {
        if ($value === null) {
            return null;
        }

        return utf8_encode($value);
    }
}
