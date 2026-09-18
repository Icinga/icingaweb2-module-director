<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Form\Element;

/**
 * Input control for booleans, gives y/n
 */
class YesNo extends OptionalYesNo
{
    public $options = array(
        'y'  => 'Yes',
        'n'  => 'No',
    );
}
