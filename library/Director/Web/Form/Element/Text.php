<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Form\Element;

use Zend_Form_Element_Text as ZfText;

class Text extends ZfText
{
    public function setValue($value)
    {
        if (\is_array($value)) {
            $value = \json_encode($value);
        }
        return parent::setValue((string) $value);
    }
}
