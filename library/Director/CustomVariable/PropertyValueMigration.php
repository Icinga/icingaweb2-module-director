<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

/**
 * Everything a stored value needs to become the new schema, for one root property
 *
 * Built once per root from a single, pre-change snapshot, before anything gets
 * stored. Applying it never reads back a value it already wrote itself, so a
 * swap, a rotation, or a rename chain resolves in one pass instead of racing.
 */
final class PropertyValueMigration
{
    /**
     * @param string $oldVarname varname the value is currently stored under
     * @param string $newVarname varname it should end up under, same as $oldVarname
     *                           unless the root itself renamed and that rename went through
     * @param string $oldRootType the root's value_type before this restore, decides how
     *                             the stored JSON is shaped (plain vs. dynamic-dictionary)
     * @param bool $wholeValueCleared true if the root itself retyped, the old value can't
     *                                survive under any schema and gets dropped outright,
     *                                whatever changed underneath it no longer matters
     * @param bool $blocked true if a legacy Data Field owns this varname, nothing below
     *                       gets touched and any in-memory schema changes were undone
     * @param PropertyValueChange[] $children changes keyed by their old key_name
     * @param string[] $fixedArrayReindexes raw binary uuids of fixed-array parents whose
     *                                      surviving children need renumbering
     */
    public function __construct(
        public readonly string $oldVarname,
        public readonly string $newVarname,
        public readonly string $oldRootType,
        public readonly bool $wholeValueCleared,
        public readonly bool $blocked,
        public readonly array $children,
        public readonly array $fixedArrayReindexes
    ) {
    }

    /**
     * A migration for a root that never existed before this restore, nothing is stored yet
     *
     * @return self
     */
    public static function nothingStoredYet(): self
    {
        return new self('', '', '', false, false, [], []);
    }

    /**
     * Whether there is nothing at all to do for this root
     *
     * @return bool
     */
    public function isNoop(): bool
    {
        return $this->oldVarname === ''
            || ($this->oldVarname === $this->newVarname
                && ! $this->wholeValueCleared
                && ! $this->blocked
                && empty($this->children)
                && empty($this->fixedArrayReindexes));
    }
}
