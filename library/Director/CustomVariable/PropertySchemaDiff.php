<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\Objects\DirectorProperty;

/**
 * Works out what a property tree's pending changes mean for its stored values,
 * before anything gets written
 *
 * Only looks at nested properties (anything with a parent). A root property's
 * own rename or delete already goes through its own varname-level checks
 * elsewhere, this class only has to worry about what sits underneath it.
 *
 * A single blocked check covers the whole tree. Every stored value under a
 * root sits in the same JSON blob, so if a legacy Data Field owns that blob,
 * it owns all of it, not just the one nested field that happened to change.
 *
 * Doesn't care what order siblings come out in, moving a stored value only
 * ever touches its own spot in the blob, never a sibling's, so nothing here
 * needs to reason about swaps or cycles. That's a director_property write
 * order problem, not a stored value one.
 */
class PropertySchemaDiff
{
    /**
     * @param CustomVariableValueCleaner $cleaner checks whether a legacy Data Field blocks cleanup
     */
    public function __construct(private CustomVariableValueCleaner $cleaner)
    {
    }

    /**
     * Work out what changed under a property tree since it was loaded
     *
     * @param DirectorProperty $root the root property to diff
     *
     * @return PropertyChange[]
     */
    public function diff(DirectorProperty $root): array
    {
        if (! $root->hasBeenLoadedFromDb()) {
            // nothing existed before this restore, so nothing can be stored
            // under it yet either
            return [];
        }

        $blocked = $this->cleaner->wouldDeleteCollideWithLegacyDatafield(
            $root->getOriginalProperty('key_name')
        );

        $changes = [];
        $this->collectChanges($root, $blocked, $changes);

        return $changes;
    }

    /**
     * Walk a property's children and collect what changed under each of them
     *
     * @param DirectorProperty $parent the property whose children to check
     * @param bool $blocked whether a legacy Data Field blocks stored value cleanup
     * @param PropertyChange[] $changes changes found so far, added to by reference
     *
     * @return void
     */
    private function collectChanges(DirectorProperty $parent, bool $blocked, array &$changes): void
    {
        $items = $parent->fetchItemsFromDb();

        $keep = [];
        foreach ($items as $item) {
            $uuid = $item->get('uuid');
            if ($uuid !== null) {
                $keep[$uuid] = true;
            }
        }

        foreach ($parent->fetchExistingChildrenFromDb() as $existingChild) {
            if (! isset($keep[$existingChild->get('uuid')])) {
                $changes[] = PropertyChange::delete($existingChild, $parent);
            }
        }

        foreach ($items as $item) {
            if (! $item->hasBeenLoadedFromDb()) {
                $this->collectChanges($item, $blocked, $changes);

                continue;
            }

            if ($item->getOriginalProperty('key_name') !== $item->get('key_name')) {
                $changes[] = PropertyChange::rename($item, $parent, ! $blocked);
            }

            if ($item->get('value_type') !== $item->getOriginalProperty('value_type')) {
                $changes[] = PropertyChange::retype($item, $parent, ! $blocked);
            }

            $this->collectChanges($item, $blocked, $changes);
        }
    }
}
