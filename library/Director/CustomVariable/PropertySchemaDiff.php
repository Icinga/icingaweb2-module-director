<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;

/**
 * Figures out what a property tree's changes mean for its stored values, before
 * anything gets written
 *
 * Builds one migration plan per root, covering the root and everything nested
 * below it. The plan runs in one pass per value, so a swap, a rotation or a
 * rename chain only ever reads the original value and never overwrites a
 * sibling's move.
 *
 * A legacy Data Field collision blocks the whole plan, not just one change,
 * since a root's stored values all live in the same JSON blob. Blocked changes
 * get undone here too, so a schema row never points at data that never moved.
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
     * Work out what changed under a property tree since it was loaded, root included
     *
     * @param DirectorProperty $root the root property to diff
     *
     * @return PropertyValueMigration
     */
    public function diff(DirectorProperty $root): PropertyValueMigration
    {
        if (! $root->hasBeenLoadedFromDb()) {
            // nothing existed before this restore, so nothing can be stored
            // under it yet either
            return PropertyValueMigration::nothingStoredYet();
        }

        $oldVarname = $root->getOriginalProperty('key_name');
        $newVarname = $root->get('key_name');
        $oldRootType = $root->getOriginalProperty('value_type');
        $newRootType = $root->get('value_type');
        $renamed = $oldVarname !== $newVarname;
        $retyped = $oldRootType !== $newRootType;

        $blocked = $renamed
            ? $this->cleaner->wouldRenameCollideWithLegacyDatafield($oldVarname, $newVarname)
            : $this->cleaner->wouldDeleteCollideWithLegacyDatafield($oldVarname);

        if ($blocked) {
            if ($renamed) {
                $root->set('key_name', $oldVarname);
            }

            if ($retyped) {
                $root->set('value_type', $oldRootType);
            }

            $this->revertSubtree($root);

            // This still keeps the name the basket actually asked for, even though the
            // rename never happens. Whoever explains the block later needs to know
            // what it would have been.
            return new PropertyValueMigration(
                oldVarname: $oldVarname,
                newVarname: $newVarname,
                oldRootType: $oldRootType,
                wholeValueCleared: false,
                blocked: true,
                children: [],
                fixedArrayReindexes: []
            );
        }

        if ($retyped) {
            // The old value no longer matches the new schema, same as a delete would.
            // Whatever changed underneath doesn't matter anymore, it's all going away.
            return new PropertyValueMigration(
                oldVarname: $oldVarname,
                newVarname: $newVarname,
                oldRootType: $oldRootType,
                wholeValueCleared: true,
                blocked: false,
                children: [],
                fixedArrayReindexes: []
            );
        }

        $fixedArrayReindexes = [];
        $children = $this->collectChanges($root, $fixedArrayReindexes);

        return new PropertyValueMigration(
            oldVarname: $oldVarname,
            newVarname: $newVarname,
            oldRootType: $oldRootType,
            wholeValueCleared: false,
            blocked: false,
            children: $children,
            fixedArrayReindexes: $fixedArrayReindexes
        );
    }

    /**
     * Walk a property's children and build a change entry for each one that moved,
     * cleared, or disappeared
     *
     * @param DirectorProperty $parent the property whose children to check
     * @param string[] $fixedArrayReindexes raw binary parent uuids needing a reindex,
     *                                       keyed by hex so the same parent is never
     *                                       queued twice, added to by reference
     *
     * @return PropertyValueChange[] keyed by each child's old key_name
     */
    private function collectChanges(DirectorProperty $parent, array &$fixedArrayReindexes): array
    {
        $items = $parent->fetchItemsFromDb();
        $isParentFixedArray = $parent->get('value_type') === 'fixed-array';

        $keep = [];
        foreach ($items as $item) {
            $uuid = $item->get('uuid');
            if ($uuid !== null) {
                $keep[$uuid] = true;
            }
        }

        $changes = [];

        foreach ($parent->fetchExistingChildrenFromDb() as $existingChild) {
            if (isset($keep[$existingChild->get('uuid')])) {
                continue;
            }

            $oldKey = $existingChild->get('key_name');
            $changes[$oldKey] = new PropertyValueChange(
                oldKey: $oldKey,
                newKey: null,
                valueCleared: true,
                preserveIndex: false,
                children: []
            );

            if ($isParentFixedArray) {
                // Its row is already gone by the time this runs, only the surviving
                // siblings' numbering still needs fixing. One entry per parent is enough.
                $rawParentUuid = DbUtil::binaryResult($parent->get('uuid'));
                $fixedArrayReindexes[bin2hex($rawParentUuid)] = $rawParentUuid;
            }
        }

        foreach ($items as $item) {
            if (! $item->hasBeenLoadedFromDb()) {
                continue;
            }

            $oldKey = $item->getOriginalProperty('key_name');
            $newKey = $item->get('key_name');
            $retyped = $item->getOriginalProperty('value_type') !== $item->get('value_type');

            // A retype drops the old value outright, whatever changed further down
            // doesn't matter anymore, same reasoning as a root retype.
            $nested = $retyped ? [] : $this->collectChanges($item, $fixedArrayReindexes);

            if ($oldKey === $newKey && ! $retyped && empty($nested)) {
                // nothing changed here or anywhere below it, the value carries straight over
                continue;
            }

            $changes[$oldKey] = new PropertyValueChange(
                oldKey: $oldKey,
                newKey: $newKey,
                valueCleared: $retyped,
                preserveIndex: $retyped && $isParentFixedArray,
                children: $nested
            );
        }

        return $changes;
    }

    /**
     * Undo every rename and retype under a blocked root, so nothing gets stored
     * with a schema the data can't follow
     */
    private function revertSubtree(DirectorProperty $parent): void
    {
        foreach ($parent->fetchItemsFromDb() as $item) {
            if (! $item->hasBeenLoadedFromDb()) {
                continue;
            }

            $oldKey = $item->getOriginalProperty('key_name');
            if ($item->get('key_name') !== $oldKey) {
                $item->set('key_name', $oldKey);
            }

            $oldType = $item->getOriginalProperty('value_type');
            if ($item->get('value_type') !== $oldType) {
                $item->set('value_type', $oldType);
            }

            $this->revertSubtree($item);
        }
    }
}
