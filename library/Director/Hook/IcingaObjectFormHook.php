<?php

// SPDX-FileCopyrightText: 2019 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Web\Hook;

abstract class IcingaObjectFormHook
{
    protected $settings = [];

    abstract public function onSetup(DirectorObjectForm $form);

    public static function callOnSetup(DirectorObjectForm $form)
    {
        /** @var static[] $implementations */
        $implementations = Hook::all('director/IcingaObjectForm');
        foreach ($implementations as $implementation) {
            $implementation->onSetup($form);
        }
    }
}
