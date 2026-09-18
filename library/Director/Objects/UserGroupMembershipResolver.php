<?php

// SPDX-FileCopyrightText: 2025 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

class UserGroupMembershipResolver extends GroupMembershipResolver
{
    protected $type = 'user';
}
