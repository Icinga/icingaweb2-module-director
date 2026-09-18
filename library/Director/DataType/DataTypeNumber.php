<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Data\ValueFilter\FilterInt;

class DataTypeNumber extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        $element = $form->createElement('text', $name)
            ->addValidator('int')
            ->addFilter(new FilterInt());

        return $element;
    }
}
