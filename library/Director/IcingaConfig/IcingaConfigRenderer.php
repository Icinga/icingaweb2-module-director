<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\IcingaConfig;

interface IcingaConfigRenderer
{
    public function toConfigString();
    public function toLegacyConfigString();
    public function __toString();
}
