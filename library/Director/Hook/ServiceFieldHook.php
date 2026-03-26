<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Hook;

use Icinga\Module\Director\Field\FieldSpec;
use Icinga\Module\Director\Objects\IcingaService;

abstract class ServiceFieldHook
{
    public function wants(IcingaService $service)
    {
        return true;
    }

    /**
     * @return FieldSpec
     */
    abstract public function getFieldSpec(IcingaService $service);
}
