<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;

class PropertyModifierURLEncode extends PropertyModifierHook
{
    public function getName()
    {
        return 'URL-encode a string';
    }


    public function transform($value)
    {
        return rawurlencode($value);
    }
}
