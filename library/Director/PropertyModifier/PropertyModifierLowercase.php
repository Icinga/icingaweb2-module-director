<?php

namespace Icinga\Module\Director\Sync;

use Icinga\Module\Director\Web\Hook\PropertyModifierHook;

class PropertyModifierLowercase extends PropertyModifierHook
{

    public function transform($value)
    {
        return strtolower($value);
    }

}
