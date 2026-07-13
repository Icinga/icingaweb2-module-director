<?php

namespace Icinga\Module\Director\Web\Form\Element;

use ipl\Html\FormElement\PasswordElement;

/**
 * A password element that never surfaces null - an empty/untouched field
 * evaluates to '', matching the value semantics of every other element type
 * used for custom variables.
 */
class SensitiveElement extends PasswordElement
{
    public function getValue()
    {
        return parent::getValue() ?? '';
    }
}
