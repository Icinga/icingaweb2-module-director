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
     * @param string $newType the item's value_type after this restore, only meaningful
     *                        when $retyped is true, checked against the stored value
     *                        before deciding to clear it
     * @param bool $retyped true if the item's value_type changed, or the item got
     *                       removed outright, either way the value only survives if
     *                       it still fits $newType
     * @param bool $preserveIndex only used when $newKey is set and the value gets
     *                            cleared, leaves a null behind at $newKey instead of
     *                            dropping it, keeps a fixed-array's sibling slots
     *                            from shifting
     * @param PropertyValueChange[] $children further changes beneath this item, keyed
     *                                         by their own old key_name, only meaningful
     *                                         when the value is carried over
     */
    public function __construct(
        public readonly string $oldKey,
        public readonly ?string $newKey,
        public readonly string $newType,
        public readonly bool $retyped,
        public readonly bool $preserveIndex,
        public readonly array $children
    ) {
    }
}
