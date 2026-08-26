<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

/**
 * Works out a safe rename order for siblings, flags which ones need a placeholder
 *
 * A rename only needs a placeholder when it's part of a real cycle, A wants B's
 * name while B wants A's. A plain chain just needs storing tail first, so nobody
 * takes a name a sibling hasn't given up yet.
 *
 * Only knows about the siblings that are actually renaming. If a rename's target
 * collides with a sibling that keeps its name, that's a real conflict this class
 * can't fix by reordering, storing it still fails the same way it always did.
 */
class PropertyRenameOrder
{
    /**
     * Work out a safe store order for the given renames, and flag any real cycles
     *
     * @param array<string, array{old: string, new: string}> $renames keyed by uuid,
     *        entries where old equals new are ignored
     *
     * @return array{order: string[], cycles: string[]} safe order to store the
     *         renames in, plus the uuids stuck in a real cycle
     */
    public function resolve(array $renames): array
    {
        $holderOf = [];
        foreach ($renames as $uuid => $rename) {
            if ($rename['old'] === $rename['new']) {
                continue;
            }

            $holderOf[$rename['old']] = $uuid;
        }

        $done = [];
        $order = [];
        $cycles = [];

        foreach ($renames as $startUuid => $startRename) {
            if (isset($done[$startUuid]) || $startRename['old'] === $startRename['new']) {
                continue;
            }

            [$path, $cycleStart] = $this->walk($startUuid, $renames, $holderOf, $done);

            if ($cycleStart !== null) {
                foreach (array_slice($path, $cycleStart) as $cycleUuid) {
                    $cycles[] = $cycleUuid;
                }
            }

            foreach (array_reverse($path) as $pathUuid) {
                $done[$pathUuid] = true;
                $order[] = $pathUuid;
            }
        }

        return ['order' => $order, 'cycles' => $cycles];
    }

    /**
     * Follow "who's holding the name I want" starting from $startUuid
     *
     * Stops at a free name, an already handled item, or a loop back onto itself.
     *
     * @param string $startUuid Where to start walking from
     * @param array<string, array{old: string, new: string}> $renames
     * @param array<string, string> $holderOf
     * @param array<string, bool> $done
     *
     * @return array{0: string[], 1: ?int} the path walked, and where in it a
     *         cycle starts, null if there's no cycle
     */
    private function walk(string $startUuid, array $renames, array $holderOf, array $done): array
    {
        $path = [];
        $positionInPath = [];
        $uuid = $startUuid;

        while (true) {
            if (isset($done[$uuid])) {
                break;
            }

            if (isset($positionInPath[$uuid])) {
                return [$path, $positionInPath[$uuid]];
            }

            $positionInPath[$uuid] = count($path);
            $path[] = $uuid;

            $targetName = $renames[$uuid]['new'];
            if (! isset($holderOf[$targetName])) {
                break;
            }

            $uuid = $holderOf[$targetName];
        }

        return [$path, null];
    }
}
