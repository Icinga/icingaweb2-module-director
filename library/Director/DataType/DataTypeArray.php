<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class DataTypeArray extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        return $form->createElement('extensibleSet', $name);
    }
}
