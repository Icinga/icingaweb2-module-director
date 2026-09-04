<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

/**
 * What happens to one old key when a stored value gets rebuilt
 *
 * Always keyed by the OLD key_name in its parent's children map, so a lookup
 * during rebuild never depends on a sibling having moved first, everyone reads
 * off the same untouched original value.
 */
final class PropertyValueChange
{
    /**
     * @param string $oldKey the key this item's value currently sits under
     * @param ?string $newKey where the value ends up, null means the slot is gone,
     *                        the schema no longer has room for it at all
     * @param bool $valueCleared true if the old value can't survive under the new
     *                           schema and must not be carried over, a retype (in
     *                           place) or an outright removal both set this
     * @param bool $preserveIndex only used when $newKey is set and $valueCleared is
     *                            true, leaves a null behind at $newKey instead of
     *                            dropping it, keeps a fixed-array's sibling slots
     *                            from shifting
     * @param PropertyValueChange[] $children further changes beneath this item, keyed
     *                                         by their own old key_name, only meaningful
     *                                         when the value is carried over
     */
    public function __construct(
        public readonly string $oldKey,
        public readonly ?string $newKey,
        public readonly bool $valueCleared,
        public readonly bool $preserveIndex,
        public readonly array $children
    ) {
    }
}
