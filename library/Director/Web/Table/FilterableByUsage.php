<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Table;

interface FilterableByUsage
{
    public function showOnlyUsed();

    public function showOnlyUnUsed();
}
