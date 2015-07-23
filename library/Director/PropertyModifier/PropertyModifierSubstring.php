<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Web\Hook\PropertyModifierHook;

class PropertyModifierSubstring extends PropertyModifierHook
{

    public function transform($value)
    {
        return substr($value, $this->settings['start'], $this->settings['end'] - $this->settings['start']);
    }

}
