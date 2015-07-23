<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Web\Hook\PropertyModifierHook;

class PropertyModifierReplace PropertyModifierHook
{

    public function transform($value)
    {
        return str_replace($this->settings['string'], $this->settings['replacement'], $value);
    }

}
