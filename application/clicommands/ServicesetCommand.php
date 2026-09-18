<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Clicommands;

/**
 * Manage Icinga Service Sets
 *
 * Use this command to show, create, modify or delete Icinga Service
 * objects
 */
class ServicesetCommand extends ServiceCommand
{
    protected $type = 'ServiceSet';
}
