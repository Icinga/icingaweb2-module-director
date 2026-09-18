<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class DataTypeTime extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        $element = $form->createElement('text', $name);

        return $element;
    }
}
