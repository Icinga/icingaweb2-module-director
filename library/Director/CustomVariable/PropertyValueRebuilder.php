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
     * Let the caller report a conflict it spotted on its own
     *
     * Some callers have to check a value's new spot before they even hand
     * it over here, so they need a way to add that conflict to the count too.
     *
     * @param int $count How many conflicts to add
     *
     * @return void
     */
    public function noteRootConflict(int $count = 1): void
    {
        $this->conflictCount += $count;
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
        if ($decodedValue === null) {
            return null;
        }

        if ($migration->retyped) {
            // a host restored in the same basket already writes its value under the
            // new type, don't clear it just because the schema's type string changed
            return $this->stillFitsType($decodedValue, $migration->newRootType) ? $decodedValue : null;
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

            if ($change->retyped) {
                if ($this->stillFitsType($value, $change->newType)) {
                    if ($this->isTaken($change->newKey, $untouchedKeys, $result)) {
                        $this->conflictCount++;
                    } else {
                        $result[$change->newKey] = $value;
                    }

                    continue;
                }

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

            if ($change->retyped) {
                if ($this->stillFitsType($value, $change->newType)) {
                    $result[] = $value;

                    continue;
                }

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

    /**
     * Check if a stored value already fits a value_type, before we clear it
     *
     * Only checks shape, not datalist terms, that's handled elsewhere already.
     * Catches the case where a value already sits in its new shape and doesn't
     * need clearing just because the schema's type string changed.
     *
     * @param mixed $value the value as currently stored
     * @param string $type the value_type to check it against
     *
     * @return bool
     */
    private function stillFitsType($value, string $type): bool
    {
        return match ($type) {
            'string', 'sensitive' => is_string($value),
            'number' => is_int($value) || is_float($value),
            'bool' => is_bool($value),
            'datalist-strict', 'datalist-non-strict' => is_string($value)
                || (is_array($value) && array_is_list($value)),
            'dynamic-array', 'fixed-array' => is_array($value) && array_is_list($value),
            'fixed-dictionary' => $this->isDictionaryShaped($value)
                && ! $this->looksLikeDynamicDictionary($value),
            'dynamic-dictionary' => $this->isDictionaryShaped($value)
                && $this->looksLikeDynamicDictionary($value),
            default => false,
        };
    }

    /**
     * Whether a value is shaped like a dictionary, empty ones included
     *
     * An empty array is technically a list too, PHP has no way to tell an
     * empty dictionary and an empty list apart once decoded, so this treats
     * one as good enough for either.
     *
     * @param mixed $value
     *
     * @return bool
     */
    private function isDictionaryShaped($value): bool
    {
        return is_array($value) && (empty($value) || ! array_is_list($value));
    }

    /**
     * Whether every entry in a dictionary value is itself a dictionary
     *
     * A fixed-dictionary's own fields can never hold a nested dictionary, the
     * schema doesn't allow it, only a dynamic-dictionary's entries look like
     * this. That's the one signal we have to tell the two apart without
     * reading the property's schema back from the database.
     *
     * @param array $value
     *
     * @return bool
     */
    private function looksLikeDynamicDictionary(array $value): bool
    {
        if (empty($value)) {
            return false;
        }

        foreach ($value as $entry) {
            if (! is_array($entry) || array_is_list($entry)) {
                return false;
            }
        }

        return true;
    }
}
