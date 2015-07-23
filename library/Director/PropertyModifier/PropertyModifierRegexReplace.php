<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Web\Hook\PropertyModifierHook;

class PropertyModifierRegexReplace extends PropertyModifierHook
{

    public function transform($value)
    {
        return preg_replace($this->settings['pattern'], $this->settings['replacement'], $value);
    }

}
