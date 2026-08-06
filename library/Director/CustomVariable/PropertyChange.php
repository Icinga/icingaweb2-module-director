<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\Objects\DirectorProperty;

/**
 * One thing that needs to happen to a nested property, worked out ahead of time
 *
 * Carries the property (and its parent) as they already sit in memory, new
 * values already set, old values still readable off them. Reading stored
 * values back has to use the old ones, the data hasn't moved yet at this point.
 */
class PropertyChange
{
    public const RENAME = 'rename';

    public const RETYPE = 'retype';

    public const DELETE = 'delete';

    private function __construct(
        public readonly string $kind,
        public readonly DirectorProperty $property,
        public readonly DirectorProperty $parent,
        public readonly bool $allowed = true
    ) {
    }

    public static function rename(DirectorProperty $property, DirectorProperty $parent, bool $allowed): self
    {
        return new self(self::RENAME, $property, $parent, $allowed);
    }

    public static function retype(DirectorProperty $property, DirectorProperty $parent, bool $allowed): self
    {
        return new self(self::RETYPE, $property, $parent, $allowed);
    }

    /**
     * A deletion always goes ahead in the schema. Only the stored value cleanup
     * can be held back by a legacy Data Field, and that's checked separately,
     * inside the cleanup call itself.
     */
    public static function delete(DirectorProperty $property, DirectorProperty $parent): self
    {
        return new self(self::DELETE, $property, $parent);
    }
}
