<?php

namespace Icinga\Module\Director\Web\Form\Element;

/**
 * Input control for booleans, gives y/n
 */
class OptionalYesNo extends Boolean
{
    public function getValue()
    {
        $value = $this->getUnfilteredValue();

        if ($value === 'y' || $value === true) {
            return 'y';
        } elseif ($value === 'n' || $value === false) {
            return 'n';
        }

        return null;
    }
}
