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
 * If a rename's new key is already taken by something that isn't part of the
 * same move, that's a real conflict. Whatever was already there keeps its key,
 * the incoming rename gets dropped and counted instead.
 *
 * dynamic-dictionary, fixed-dictionary and fixed-array may only ever be a root's
 * own type (see DirectorProperty::NON_NESTABLE_TYPES), so the root-type branching
 * only happens once, at the top. Everything nested below that is always a plain
 * associative container.
 */
class PropertyValueRebuilder
{
    /** @var int Count of renamed values dropped because their new key was taken */
    private int $conflictCount = 0;

    /**
     * How many renamed values got dropped because their new key was already taken
     *
     * @return int
     */
    public function getConflictCount(): int
    {
        return $this->conflictCount;
    }

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
     * If a key is already taken by something that never moved, that value wins.
     * The incoming rename gets dropped instead of overwriting it.
     *
     * @param array $item
     * @param PropertyValueChange[] $changesByOldKey
     *
     * @return array
     */
    private function rebuildContainer(array $item, array $changesByOldKey): array
    {
        // Worked out once up front, so the result never depends on the order
        // of the stored data.
        $untouchedKeys = array_diff_key($item, $changesByOldKey);

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
                    if ($this->isTaken($change->newKey, $untouchedKeys, $result)) {
                        $this->conflictCount++;
                    } else {
                        $result[$change->newKey] = null;
                    }
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

            if ($this->isTaken($change->newKey, $untouchedKeys, $result)) {
                $this->conflictCount++;

                continue;
            }

            $result[$change->newKey] = $newValue;
        }

        return $result;
    }

    /**
     * Whether this key is already taken, either by something that never moved
     * or by an earlier rename in this same pass
     *
     * @param string $key
     * @param array  $untouchedKeys
     * @param array  $result
     *
     * @return bool
     */
    private function isTaken(string $key, array $untouchedKeys, array $result): bool
    {
        return array_key_exists($key, $untouchedKeys) || array_key_exists($key, $result);
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
