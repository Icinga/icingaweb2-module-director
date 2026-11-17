<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use PDO;

/**
 * Change of the sources of an object, and of the custom variable values that depend on them
 *
 * An object reaches properties through its sources, see
 * {@see CustomPropertySources}. A write that changes the imports of an object,
 * or the check command of a host or service, changes these sources for the
 * object and for everything below it. Values can then be linked to a property
 * that their holder no longer reaches, or stay unlinked although their holder
 * now reaches a property of that name.
 *
 * Capture the sources before the write and apply the change after it. A
 * linked value is removed from every holder that lost a source attaching its
 * property and no longer reaches the property, with one activity log entry per
 * holder. Every other value whose name belongs to a property of a source that
 * its holder lost or gained gets the link its holder's sources give it. A
 * holder can gain or lose other sources than the object, for example the check
 * command of another import once the object's own check command changes. No
 * value changes otherwise.
 *
 * The holders are the object and every object of its type below it. For a
 * command, every host and service is a holder too, because whether a host or
 * service uses a command depends on its check command, not on its imports.
 *
 * The number of queries does not grow with the number of holders, except for
 * a few queries per thousand holders, and for the activity log entry and the
 * delete of each holder that loses a value.
 *
 * @phpstan-type LostRows array<string, array<int, array<string, array{uuid: string, varname: string}>>>
 * @phpstan-type Links array<string, array<int, array{0: int, 1: string, 2: ?string}>>
 * @phpstan-type SourceDiff array{lost: string[], gained: string[]}
 * @phpstan-type LossReasons array<string, array<int, array<string, non-empty-list<'imports'|'command'>>>>
 */
class PropertySourceChange
{
    /**
     * Create a new PropertySourceChange
     *
     * @param IcingaObject          $object The object whose sources may change
     * @param CustomPropertySources $before The sources as stored before the change, with the object loaded
     */
    protected function __construct(
        protected readonly IcingaObject $object,
        protected readonly CustomPropertySources $before
    ) {
    }

    /**
     * Capture the stored sources of an object before a write changes them
     *
     * @param IcingaObject           $object
     * @param ?CustomPropertySources $sources The sources as stored, to share between several objects that are
     *   captured before any of them is written
     *
     * @return ?self Null for an object that is not stored yet, whose type has no imports or custom variables,
     *   or whose imports and check command are not modified
     */
    public static function capture(IcingaObject $object, ?CustomPropertySources $sources = null): ?self
    {
        if (! static::tracks($object) || ! $object->hasBeenLoadedFromDb() || ! static::changesSources($object)) {
            return null;
        }

        $sources ??= new CustomPropertySources($object->getConnection());
        // The objects below are not written, they may be loaded later. This one has to be loaded now.
        $sources->getStoredSourceKeys($object->getShortTableName(), [(int) $object->get('id')]);

        return new self($object, $sources);
    }

    /**
     * Remove the values that lost their source and update the links of the others
     *
     * Call this once the write is stored, in the same transaction.
     *
     * @param bool                   $removeLostValues Whether to remove the values of lost properties, or only to
     *   unlink them
     * @param ?CustomPropertySources $sources          The sources as stored now, to share between several objects
     *
     * @return void
     */
    public function apply(bool $removeLostValues = true, ?CustomPropertySources $sources = null): void
    {
        $db = $this->object->getConnection();

        // Readers later in this request have to see the stored imports
        IcingaTemplateRepository::clear();

        [$lostRows, $links] = static::findChanges(
            $this->object,
            $this->before,
            $sources ?? new CustomPropertySources($db),
            $removeLostValues
        );

        if (! empty($lostRows)) {
            // the object's own values may have been written apart from the object, e.g. by a REST PUT
            $object = $this->object::loadWithAutoIncId((int) $this->object->get('id'), $db);
            foreach ($lostRows as $holderType => $rowsByHolderId) {
                PropertyDetachmentCleaner::removeValues($holderType, $rowsByHolderId, $object, $db);
            }
        }

        foreach ($links as $holderType => $holderLinks) {
            static::storeLinks($holderType, $holderLinks, $db);
        }
    }

    /**
     * Get the names of the values that saving an object with other imports or another check command removes
     *
     * Reads only. The objects below the given one are checked with their own
     * imports and check commands, so the result names exactly what applying
     * the change would remove.
     *
     * @param IcingaObject $object             A stored object
     * @param ?string[]    $importNames        Pending imports, null to keep the stored ones
     * @param bool         $changeCheckCommand Whether $checkCommand replaces the stored own check command
     * @param ?string      $checkCommand       The pending own check command, null for none
     *
     * @return array{imports: string[], command: string[]} Names of the values that a lost template, or a lost
     *   check command or command import, removes. A name can be in both lists.
     */
    public static function previewLoss(
        IcingaObject $object,
        ?array $importNames,
        bool $changeCheckCommand = false,
        ?string $checkCommand = null
    ): array {
        $result = ['imports' => [], 'command' => []];
        if (! static::tracks($object) || ! $object->hasBeenLoadedFromDb()) {
            return $result;
        }

        $importsChange = $importNames !== null && $importNames !== $object->imports()->listOriginalImportNames();
        if (! $importsChange && ! $changeCheckCommand) {
            return $result;
        }

        $type = $object->getShortTableName();
        $id = (int) $object->get('id');
        $before = new CustomPropertySources($object->getConnection());
        // Load what both views share before copying, the pending state goes into the copy only
        $before->getStoredSourceKeys($type, [$id]);
        $after = clone $before;

        if ($importNames !== null) {
            $after->setPendingImports($object, $importNames);
        }

        if ($changeCheckCommand && CustomPropertySources::followsCheckCommand($type)) {
            $after->setPendingCheckCommand($object, $checkCommand);
        }

        [, , $lossReasons] = static::findChanges($object, $before, $after, true);
        foreach ($lossReasons as $reasonsByHolderId) {
            foreach ($reasonsByHolderId as $reasons) {
                foreach ($reasons as $varname => $varReasons) {
                    foreach ($varReasons as $reason) {
                        $result[$reason][$varname] = $varname;
                    }
                }
            }
        }

        $result['imports'] = array_values($result['imports']);
        $result['command'] = array_values($result['command']);
        sort($result['imports']);
        sort($result['command']);

        return $result;
    }

    /**
     * Check whether values of the given object can depend on its sources
     *
     * @param IcingaObject $object
     *
     * @return bool
     */
    public static function tracks(IcingaObject $object): bool
    {
        return $object->supportsImports() && $object->supportsCustomVars();
    }

    /**
     * Check whether the imports or the own check command of the given object are modified
     *
     * @param IcingaObject $object
     *
     * @return bool
     */
    protected static function changesSources(IcingaObject $object): bool
    {
        // also resolves a check command set by name, so that its id counts as modified
        if (! $object->hasBeenModified()) {
            return false;
        }

        if ($object->imports()->hasBeenModified()) {
            return true;
        }

        return CustomPropertySources::followsCheckCommand($object->getShortTableName())
            && in_array('check_command_id', $object->listModifiedProperties(), true);
    }

    /**
     * Find the values that lose their property and the links that change
     *
     * A linked value is lost when its holder lost a source that attaches its
     * property and no longer reaches the property in another way. The holders
     * of another type than the object, the hosts and services below a changed
     * command, lose what the command lost.
     *
     * @param IcingaObject          $object           The object whose sources changed
     * @param CustomPropertySources $before           The sources before the change
     * @param CustomPropertySources $after            The sources after the change
     * @param bool                  $removeLostValues Whether to report lost values, or to unlink them instead
     *
     * @return array{0: LostRows, 1: Links, 2: LossReasons}
     */
    protected static function findChanges(
        IcingaObject $object,
        CustomPropertySources $before,
        CustomPropertySources $after,
        bool $removeLostValues
    ): array {
        $type = $object->getShortTableName();
        $objectId = (int) $object->get('id');

        // The objects below reach the object's sources through it, unchanged sources leave them unchanged too
        $objectDiff = static::diffSources($before, $after, $type, [$objectId])[$objectId] ?? null;
        if ($objectDiff === null) {
            return [[], [], []];
        }

        $lostRows = [];
        $links = [];
        $lossReasons = [];
        $db = $object->getConnection();
        foreach ($type === 'command' ? ['command', 'host', 'service'] : [$type] as $holderType) {
            if ($holderType === $type) {
                $holderIds = array_merge([$objectId], $after->listDescendantIds($type, $objectId));
                $diffs = static::diffSources($before, $after, $type, $holderIds);
            } else {
                // The hosts and services that use the command lose or gain only what the command does
                $holderIds = null;
                $diffs = [0 => $objectDiff];
            }

            $namesByDiff = [];
            $reasonsByDiff = [];
            foreach ($diffs as $diffId => $diff) {
                $namesByDiff[$diffId] = array_flip(static::listChangedNames($before, $after, $diff));
                $reasonsByDiff[$diffId] = static::listLossReasons($before, $type, $diff['lost']);
            }

            $names = empty($namesByDiff) ? [] : array_keys(array_replace(...array_values($namesByDiff)));
            $rows = static::fetchValues($holderType, $names, $db, $holderIds === null ? null : array_keys($diffs));
            if ($holderIds !== null) {
                $rows = array_filter($rows, fn($row) => isset($namesByDiff[$row['holder']][$row['varname']]));
            }

            if (empty($rows)) {
                continue;
            }

            $reachable = $after->getReachablePropertyUuids(
                $holderType,
                array_values(array_unique(array_column($rows, 'holder')))
            );
            foreach ($rows as $row) {
                $target = $reachable[$row['holder']][$row['varname']] ?? null;
                if ($target === $row['uuid']) {
                    continue;
                }

                $reasons = $row['uuid'] === null
                    ? []
                    : $reasonsByDiff[$holderIds === null ? 0 : $row['holder']][$row['uuid']] ?? [];
                if ($removeLostValues && ! empty($reasons)) {
                    $lostRows[$holderType][$row['holder']][bin2hex($row['uuid'])] = [
                        'uuid'    => $row['uuid'],
                        'varname' => $row['varname'],
                    ];
                    $lossReasons[$holderType][$row['holder']][$row['varname']] = $reasons;
                } else {
                    $links[$holderType][] = [$row['holder'], $row['varname'], $target];
                }
            }
        }

        return [$lostRows, $links, $lossReasons];
    }

    /**
     * Tell for each property attached to the given lost sources whether lost templates or lost commands attached it
     *
     * The commands that a command imports count as its imports. For a host
     * or service, and for the hosts and services below a changed command, the
     * reason is seen from the changed object.
     *
     * @param CustomPropertySources $before
     * @param string                $type     Short table name of the changed object
     * @param string[]              $lostKeys
     *
     * @return array<string, non-empty-list<'imports'|'command'>> By binary property uuid
     */
    protected static function listLossReasons(CustomPropertySources $before, string $type, array $lostKeys): array
    {
        $templateKeys = [];
        $commandKeys = [];
        foreach ($lostKeys as $key) {
            if (str_starts_with($key, $type . ':')) {
                $templateKeys[] = $key;
            } else {
                $commandKeys[] = $key;
            }
        }

        $reasons = [];
        foreach (array_keys($before->getAttachedProperties($templateKeys)) as $uuid) {
            $reasons[$uuid][] = 'imports';
        }

        foreach (array_keys($before->getAttachedProperties($commandKeys)) as $uuid) {
            $reasons[$uuid][] = 'command';
        }

        return $reasons;
    }

    /**
     * Compare the sources of stored objects before and after a change
     *
     * @param CustomPropertySources $before
     * @param CustomPropertySources $after
     * @param string                $type   Short table name of the objects, e.g. "host"
     * @param int[]                 $ids
     *
     * @return array<int, SourceDiff> The source keys each object lost and gained, only for objects with a change
     */
    protected static function diffSources(
        CustomPropertySources $before,
        CustomPropertySources $after,
        string $type,
        array $ids
    ): array {
        $beforeKeys = $before->getStoredSourceKeys($type, $ids);
        $afterKeys = $after->getStoredSourceKeys($type, $ids);

        $diffs = [];
        foreach ($ids as $id) {
            $lost = array_values(array_diff($beforeKeys[$id], $afterKeys[$id]));
            $gained = array_values(array_diff($afterKeys[$id], $beforeKeys[$id]));
            if (! empty($lost) || ! empty($gained)) {
                $diffs[$id] = ['lost' => $lost, 'gained' => $gained];
            }
        }

        return $diffs;
    }

    /**
     * Get the names of the properties attached to lost and gained sources
     *
     * @param CustomPropertySources $before
     * @param CustomPropertySources $after
     * @param SourceDiff            $diff
     *
     * @return string[]
     */
    protected static function listChangedNames(
        CustomPropertySources $before,
        CustomPropertySources $after,
        array $diff
    ): array {
        return array_values(array_unique(array_merge(
            $before->getAttachedProperties($diff['lost']),
            $after->getAttachedProperties($diff['gained'])
        )));
    }

    /**
     * Fetch the stored values with the given names
     *
     * @param string   $type      Short table name of the holders, e.g. "host"
     * @param string[] $names
     * @param Db       $db
     * @param ?int[]   $holderIds Only the values of these holders, null for the values of every holder
     *
     * @return array<int, array{holder: int, varname: string, uuid: ?string}>
     */
    protected static function fetchValues(string $type, array $names, Db $db, ?array $holderIds = null): array
    {
        if (empty($names) || $holderIds === []) {
            return [];
        }

        $dba = $db->getDbAdapter();
        $query = $dba->select()
            ->from('icinga_' . $type . '_var', [
                'holder'  => $type . '_id',
                'varname' => 'varname',
                'uuid'    => 'property_uuid',
            ])
            ->where('varname IN (?)', $names);

        $queries = [];
        if ($holderIds === null) {
            $queries[] = $query;
        } else {
            foreach (array_chunk($holderIds, 1000) as $chunk) {
                $queries[] = (clone $query)->where($type . '_id IN (?)', $chunk);
            }
        }

        $rows = [];
        foreach ($queries as $chunkQuery) {
            foreach ($dba->fetchAll($chunkQuery, [], PDO::FETCH_ASSOC) as $row) {
                $row = DbUtil::normalizeRow($row);
                $rows[] = [
                    'holder'  => (int) $row['holder'],
                    'varname' => $row['varname'],
                    'uuid'    => $row['uuid'],
                ];
            }
        }

        return $rows;
    }

    /**
     * Store new links for values without changing them
     *
     * @param string                                   $type  Short table name of the holders, e.g. "host"
     * @param array<int, array{0: int, 1: string, 2: ?string}> $links Holder id, value name and binary property uuid
     * @param Db                                       $db
     *
     * @return void
     */
    protected static function storeLinks(string $type, array $links, Db $db): void
    {
        $holdersByTarget = [];
        foreach ($links as [$holderId, $varname, $uuid]) {
            $target = $varname . "\0" . ($uuid === null ? '' : bin2hex($uuid));
            $holdersByTarget[$target]['varname'] = $varname;
            $holdersByTarget[$target]['uuid'] = $uuid;
            $holdersByTarget[$target]['holders'][] = $holderId;
        }

        $dba = $db->getDbAdapter();
        foreach ($holdersByTarget as $target) {
            foreach (array_chunk($target['holders'], 1000) as $holderIds) {
                $dba->update(
                    'icinga_' . $type . '_var',
                    [
                        'property_uuid' => $target['uuid'] === null
                            ? null
                            : DbUtil::quoteBinaryCompat($target['uuid'], $dba),
                    ],
                    $dba->quoteInto('varname = ?', $target['varname'])
                    . ' AND ' . $dba->quoteInto($type . '_id IN (?)', $holderIds)
                );
            }
        }
    }
}
