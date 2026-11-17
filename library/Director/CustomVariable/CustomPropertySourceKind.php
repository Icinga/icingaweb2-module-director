<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

/**
 * How an object reaches the properties attached to one of its sources
 */
enum CustomPropertySourceKind
{
    /** The object itself */
    case Own;

    /** A template of the object's own type that the object imports, directly or indirectly */
    case Template;

    /**
     * A command
     *
     * For a host or service, the effective check command and every command it imports. For a
     * command, a plain command that it imports.
     */
    case Command;
}
