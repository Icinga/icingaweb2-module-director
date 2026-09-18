<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Import\PurgeStrategy;

class PurgeNothingPurgeStrategy extends PurgeStrategy
{
    public function listObjectsToPurge()
    {
        return array();
    }
}
