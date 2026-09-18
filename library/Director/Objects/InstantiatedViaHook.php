<?php

// SPDX-FileCopyrightText: 2022 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Hook\PropertyModifierHook;

interface InstantiatedViaHook
{
    /**
     * @return mixed|PropertyModifierHook|JobHook
     */
    public function getInstance();
}
