<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

/**
 * Turns one stored value into its new shape, given a PropertyValueMigration
 *
 * Pure, no DB access. Always reads from the original decoded value and writes into
 * a fresh array, never mutates the input in place. That's what makes a swap, a
 * rotation, or a rename chain safe to apply as a single pass, every read sees the
 * same untouched original, nobody's move can overwrite a sibling's or chase a path
 * that's already moved.
 *
 * dynamic-dictionary, fixed-dictionary and fixed-array may only ever be a root's
 * own type (see DirectorProperty::NON_NESTABLE_TYPES), so the root-type branching
 * only happens once, at the top. Everything nested below that is always a plain
 * associative container.
 */
class PropertyValueRebuilder
{
    /**
     * Rebuild one decoded stored value for its root's migration
     *
     * @param mixed $decodedValue the value as currently stored, already json_decoded
     * @param PropertyValueMigration $migration
     *
     * @return mixed the rebuilt value, or null if the value must be dropped entirely
     */
    public function rebuildRootValue($decodedValue, PropertyValueMigration $migration)
    {
        if ($migration->wholeValueCleared || $decodedValue === null) {
            return null;
        }

        if (empty($migration->children) || ! is_array($decodedValue)) {
            return $decodedValue;
        }

        if ($migration->oldRootType === 'dynamic-dictionary') {
            // Entries themselves are kept even when what's under them empties out,
            // only the fields the schema tracks inside each entry move or disappear.
            return $this->rebuildDynamicDictionaryEntries($decodedValue, $migration->children);
        }

        if ($migration->oldRootType === 'fixed-array') {
            $rebuilt = $this->rebuildFixedArrayChildren($decodedValue, $migration->children);

            return empty($rebuilt) ? null : $rebuilt;
        }

        $rebuilt = $this->rebuildContainer($decodedValue, $migration->children);

        return empty($rebuilt) ? null : $rebuilt;
    }

    /**
     * Rebuild one plain associative container by key
     *
     * A key with no matching change carries its value over untouched. A dropped
     * key (its change has no new key, or it cleared without preserving its slot)
     * is left out entirely. A cleared-but-preserved key keeps its new key with a
     * null value. Everything else keeps or moves its value under its new key,
     * recursing first if anything changed further down.
     *
     * @param array $item
     * @param PropertyValueChange[] $changesByOldKey
     *
     * @return array
     */
    private function rebuildContainer(array $item, array $changesByOldKey): array
    {
        $result = [];

        foreach ($item as $key => $value) {
            $change = $changesByOldKey[$key] ?? null;

            if ($change === null) {
                $result[$key] = $value;

                continue;
            }

            if ($change->newKey === null) {
                continue;
            }

            if ($change->valueCleared) {
                if ($change->preserveIndex) {
                    $result[$change->newKey] = null;
                }

                continue;
            }

            $newValue = $value;
            if (! empty($change->children) && is_array($value)) {
                $newValue = $this->rebuildContainer($value, $change->children);
            }

            // An emptied nested container leaves nothing worth keeping, same as
            // the schema no longer having anything left under it.
            if (is_array($newValue) && empty($newValue)) {
                continue;
            }

            $result[$change->newKey] = $newValue;
        }

        return $result;
    }

    /**
     * Does the same rebuild for every entry of a dynamic dictionary
     *
     * @param array $dynamicDictionaryValue
     * @param PropertyValueChange[] $changesByOldKey
     *
     * @return array
     */
    private function rebuildDynamicDictionaryEntries(array $dynamicDictionaryValue, array $changesByOldKey): array
    {
        $result = [];

        foreach ($dynamicDictionaryValue as $entryKey => $entryValue) {
            if (! is_array($entryValue)) {
                $result[$entryKey] = $entryValue;

                continue;
            }

            $rebuiltEntry = $this->rebuildContainer($entryValue, $changesByOldKey);
            // Keep the entry itself, an empty {} still means "this one has no vars left"
            // rather than "this entry never existed".
            $result[$entryKey] = empty($rebuiltEntry) ? (object) [] : $rebuiltEntry;
        }

        return $result;
    }

    /**
     * Rebuild a fixed-array's children, keeping their relative order and closing
     * any gap a removal leaves behind
     *
     * A json_encode()d PHP list only stays a JSON array as long as its keys are
     * sequential from 0, so this always returns a freshly indexed list rather
     * than reusing the old positions.
     *
     * @param array $item
     * @param PropertyValueChange[] $changesByOldKey
     *
     * @return array
     */
    private function rebuildFixedArrayChildren(array $item, array $changesByOldKey): array
    {
        $result = [];

        foreach ($item as $key => $value) {
            $change = $changesByOldKey[$key] ?? null;

            if ($change === null) {
                $result[] = $value;

                continue;
            }

            if ($change->newKey === null) {
                continue;
            }

            if ($change->valueCleared) {
                if ($change->preserveIndex) {
                    $result[] = null;
                }

                continue;
            }

            $newValue = $value;
            if (! empty($change->children) && is_array($value)) {
                $newValue = $this->rebuildContainer($value, $change->children);
            }

            $result[] = $newValue;
        }

        return $result;
    }
}
