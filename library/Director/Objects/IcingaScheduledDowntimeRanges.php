<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

use Countable;
use Iterator;
use Icinga\Module\Director\IcingaConfig\IcingaConfigRenderer;

class IcingaScheduledDowntimeRanges extends IcingaRanges implements Iterator, Countable, IcingaConfigRenderer
{
    protected $rangeClass = IcingaScheduledDowntimeRange::class;
    protected $objectIdColumn = 'scheduled_downtime_id';

    public function toLegacyConfigString()
    {
        return '';
    }
}
