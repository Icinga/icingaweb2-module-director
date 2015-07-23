<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Web\Hook\PropertyModifierHook;

class PropertyModifierStripDomain extends PropertyModifierHook
{

    public function transform($value)
    {
        return preg_replace($this->settings['domain'], "", $value);
    }

}
