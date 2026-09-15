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
     * @param string $newVarname varname the rename was aiming for, matches the current name
     *                           unless the root itself renamed. Stays set this way even when
     *                           blocked, so the block can still be explained
     * @param string $oldRootType the root's value_type before this restore, decides how
     *                             the stored JSON is shaped (plain vs. dynamic-dictionary)
     * @param string $newRootType the root's value_type after this restore, only meaningful
     *                            when $retyped is true, checked against the stored value
     *                            before deciding to clear it
     * @param bool $retyped true if the root itself retyped, the old value only survives
     *                       if it still fits $newRootType, whatever changed underneath it
     *                       no longer matters either way
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
        public readonly string $newRootType,
        public readonly bool $retyped,
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
        return new self('', '', '', '', false, false, [], []);
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
                && ! $this->retyped
                && ! $this->blocked
                && empty($this->children)
                && empty($this->fixedArrayReindexes));
    }
}
