<?php

// SPDX-FileCopyrightText: 2022 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Db\Branch;

use Icinga\Authentication\Auth;

interface PreferredBranchSupport
{
    public function hasPreferredBranch(Auth $auth): bool;
}
