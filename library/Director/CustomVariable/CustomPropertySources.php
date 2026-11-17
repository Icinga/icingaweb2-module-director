<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Exception\NestingError;
use Icinga\Module\Director\Objects\IcingaObject;
use PDO;

/**
 * Where the custom properties of Icinga objects come from
 *
 * An object reaches the properties attached to itself and to the templates it
 * imports, directly or indirectly. A host or service also reaches the
 * properties attached to its effective check command and to every command that
 * command imports. The effective check command is the one the rendered
 * configuration uses. The object's own check command wins, otherwise the
 * imports are searched from the last to the first, each one depth first.
 *
 * An instance loads the templates, their imports and the property
 * attachments of each object type once, and answers later questions from
 * memory. Objects that are not templates are loaded the first time a method
 * asks about them, with two queries per thousand objects. Finding the objects
 * below a template costs one more query per thousand templates involved. No
 * method runs a query per object. Create a new instance after a write that
 * changes imports, check commands or attachments.
 *
 * @phpstan-import-type Attachment from CustomPropertySource
 */
class CustomPropertySources
{
    /** @var string[] Object types that also reach the properties of their check command */
    protected const CHECK_COMMAND_TYPES = ['host', 'service'];

    /** @var int Maximum number of object ids in one lookup */
    protected const CHUNK_SIZE = 1000;

    /** @var array<string, array<int, array{name: string, template: bool, command: ?int}>> Loaded objects */
    protected array $objects = [];

    /** @var array<string, array<int, int[]>> Parent ids of the loaded objects, in import order */
    protected array $parents = [];

    /** @var array<string, array<string, int>> Ids of importable objects by name: templates, or any command */
    protected array $importableIds = [];

    /** @var array<string, array<int, array<string, Attachment>>> Root property attachments by object id and name */
    protected array $attachments = [];

    /** @var array<string, array<int, int[]>> Parent ids that replace the stored ones */
    protected array $pendingParents = [];

    /** @var array<string, array<int, ?int>> Check command ids that replace the stored ones */
    protected array $pendingCommands = [];

    /** @var array<string, array<int, ?int>> Effective check command ids found so far */
    protected array $effectiveCommands = [];

    /**
     * Create a new CustomPropertySources
     *
     * @param Db $db The database to read the objects and attachments from
     */
    public function __construct(protected readonly Db $db)
    {
    }

    /**
     * Get the sources of the given object, in the order the object reaches them
     *
     * The object itself comes first, then its templates in import order, then
     * its effective check command followed by the commands it imports. Uses the
     * object's current imports and check command, stored or not.
     *
     * @param IcingaObject $object
     *
     * @return CustomPropertySource[]
     */
    public function getSources(IcingaObject $object): array
    {
        $type = $object->getShortTableName();
        $this->loadType($type);

        $id = $object->hasBeenLoadedFromDb() ? (int) $object->get('id') : null;
        $sources = [new CustomPropertySource(
            CustomPropertySourceKind::Own,
            $type,
            $id,
            (string) $object->getObjectName(),
            $object->isTemplate(),
            $id === null ? [] : $this->attachments[$type][$id] ?? []
        )];

        $parentIds = $this->getObjectParentIds($object);
        foreach ($this->listAncestorIds($type, $parentIds) as $ancestorId) {
            $sources[] = $this->createSource($type, $ancestorId);
        }

        if (! static::followsCheckCommand($type)) {
            return $sources;
        }

        $commandId = $this->getObjectCommandId($object);
        if ($commandId === null) {
            foreach (array_reverse($parentIds) as $parentId) {
                $commandId = $this->getEffectiveCommandId($type, $parentId);
                if ($commandId !== null) {
                    break;
                }
            }
        }

        if ($commandId !== null) {
            foreach ($this->listCommandChain($commandId) as $chainId) {
                $sources[] = $this->createSource('command', $chainId, CustomPropertySourceKind::Command);
            }
        }

        return $sources;
    }

    /**
     * Get the root properties the given object reaches, keyed by name
     *
     * @param IcingaObject $object
     *
     * @return array<string, Attachment>
     */
    public function getReachableProperties(IcingaObject $object): array
    {
        $properties = [];
        foreach ($this->getSources($object) as $source) {
            $properties += $source->attachments;
        }

        return $properties;
    }

    /**
     * Get the root properties that stored objects reach
     *
     * Pending imports and check commands set on this instance apply.
     *
     * @param string $type Short table name of the objects, e.g. "host"
     * @param int[]  $ids
     *
     * @return array<int, array<string, string>> Binary property uuids by name, for each object id
     */
    public function getReachablePropertyUuids(string $type, array $ids): array
    {
        $this->loadObjects($type, $ids);

        $result = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $uuids = [];
            foreach ($this->listStoredSources($type, $id) as [$sourceType, $sourceId]) {
                foreach ($this->attachments[$sourceType][$sourceId] ?? [] as $name => $attachment) {
                    $uuids[$name] ??= $attachment['uuid'];
                }
            }

            $result[$id] = $uuids;
        }

        return $result;
    }

    /**
     * Get the keys of the sources of stored objects, see {@see CustomPropertySource::getKey()}
     *
     * Pending imports and check commands set on this instance apply.
     *
     * @param string $type Short table name of the objects, e.g. "host"
     * @param int[]  $ids
     *
     * @return array<int, string[]> Source keys for each object id
     */
    public function getStoredSourceKeys(string $type, array $ids): array
    {
        $this->loadObjects($type, $ids);

        $result = [];
        foreach ($ids as $id) {
            $keys = [];
            foreach ($this->listStoredSources($type, (int) $id) as [$sourceType, $sourceId]) {
                $keys[] = $sourceType . ':' . $sourceId;
            }

            $result[(int) $id] = $keys;
        }

        return $result;
    }

    /**
     * Get the root properties attached to the given sources
     *
     * @param string[] $sourceKeys Keys as returned by {@see getStoredSourceKeys()}
     *
     * @return array<string, string> Property names by binary property uuid
     */
    public function getAttachedProperties(array $sourceKeys): array
    {
        $properties = [];
        foreach ($sourceKeys as $key) {
            [$type, $id] = explode(':', $key, 2);
            $this->loadType($type);
            foreach ($this->attachments[$type][(int) $id] ?? [] as $name => $attachment) {
                $properties[$attachment['uuid']] = $name;
            }
        }

        return $properties;
    }

    /**
     * List the stored objects that import the given one, directly or indirectly
     *
     * Loads the objects it finds. Pending imports set on this instance apply.
     *
     * @param string $type Short table name of the objects, e.g. "host"
     * @param int    $id
     *
     * @return int[]
     */
    public function listDescendantIds(string $type, int $id): array
    {
        $this->loadObjects($type, [$id]);
        if (! isset($this->objects[$type][$id])) {
            return [];
        }

        // Every object that can be imported is loaded already, so the ones that import others are known
        $children = [];
        foreach (array_keys($this->objects[$type]) as $childId) {
            foreach ($this->getParentIds($type, $childId) as $parentId) {
                $children[$parentId][] = $childId;
            }
        }

        $descendants = [];
        $queue = [$id];
        while (($parentId = array_pop($queue)) !== null) {
            foreach ($children[$parentId] ?? [] as $childId) {
                if ($childId !== $id && ! isset($descendants[$childId])) {
                    $descendants[$childId] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        // Objects that are not templates import templates but cannot be imported, look up the ones not loaded yet
        if ($type === 'command' || ! $this->objects[$type][$id]['template']) {
            return array_values($descendants);
        }

        $parentIds = array_merge([$id], array_values($descendants));
        $dba = $this->db->getDbAdapter();
        $found = [];
        foreach (array_chunk($parentIds, static::CHUNK_SIZE) as $chunk) {
            $query = $dba->select()
                ->distinct()
                ->from(['i' => 'icinga_' . $type . '_inheritance'], ['id' => "i.{$type}_id"])
                ->where("i.parent_{$type}_id IN (?)", $chunk);
            foreach ($dba->fetchCol($query) as $childId) {
                if (! isset($this->objects[$type][(int) $childId])) {
                    $found[] = (int) $childId;
                }
            }
        }

        $this->loadObjects($type, $found);
        foreach ($found as $childId) {
            if (! empty(array_intersect($this->getParentIds($type, $childId), $parentIds))) {
                $descendants[$childId] = $childId;
            }
        }

        return array_values($descendants);
    }

    /**
     * Let a stored object import the given templates instead of its stored imports
     *
     * Names that match no importable object are skipped.
     *
     * @param IcingaObject $object
     * @param string[]     $importNames
     *
     * @return $this
     */
    public function setPendingImports(IcingaObject $object, array $importNames): static
    {
        $type = $object->getShortTableName();
        $this->loadType($type);

        $parentIds = [];
        foreach ($importNames as $name) {
            if (isset($this->importableIds[$type][$name])) {
                $parentIds[] = $this->importableIds[$type][$name];
            }
        }

        $this->pendingParents[$type][(int) $object->get('id')] = $parentIds;
        $this->effectiveCommands = [];

        return $this;
    }

    /**
     * Let a stored host or service use the given check command instead of its stored one
     *
     * @param IcingaObject $object
     * @param ?string      $commandName Null for no own check command. A name that matches no command counts as null.
     *
     * @return $this
     */
    public function setPendingCheckCommand(IcingaObject $object, ?string $commandName): static
    {
        $this->loadType('command');

        $this->pendingCommands[$object->getShortTableName()][(int) $object->get('id')] = $commandName === null
            ? null
            : $this->importableIds['command'][$commandName] ?? null;
        $this->effectiveCommands = [];

        return $this;
    }

    /**
     * Check whether objects of the given type reach the properties of their check command
     *
     * @param string $type Short table name, e.g. "host"
     *
     * @return bool
     */
    public static function followsCheckCommand(string $type): bool
    {
        return in_array($type, static::CHECK_COMMAND_TYPES, true);
    }

    /**
     * Load the importable objects, their inheritance and every root property attachment of a type
     *
     * @param string $type
     *
     * @return void
     */
    protected function loadType(string $type): void
    {
        if (isset($this->objects[$type])) {
            return;
        }

        $this->objects[$type] = [];
        $this->parents[$type] = [];
        $this->importableIds[$type] = [];
        $this->attachments[$type] = [];

        $dba = $this->db->getDbAdapter();
        $table = 'icinga_' . $type;

        // A command can import plain and external commands, every other type imports templates only
        $query = $dba->select()->from(['o' => $table], $this->objectColumns($type));
        $parentQuery = $this->parentQuery($type);
        if ($type !== 'command') {
            $query->where('o.object_type = ?', 'template');
            $parentQuery
                ->join(['o' => $table], "o.id = i.{$type}_id", [])
                ->where('o.object_type = ?', 'template');
        }

        foreach ($dba->fetchAll($query, [], PDO::FETCH_ASSOC) as $row) {
            $this->addObject($type, $row);
            $this->importableIds[$type][$row['name']] = (int) $row['id'];
        }

        $this->addParents($type, $dba->fetchAll($parentQuery, [], PDO::FETCH_ASSOC));

        $attachmentQuery = $dba->select()
            ->from(['iop' => $table . '_property'], ['required' => 'iop.required'])
            ->join(['o' => $table], "o.uuid = iop.{$type}_uuid", ['object_id' => 'o.id'])
            ->join(['dp' => 'director_property'], 'dp.uuid = iop.property_uuid', [
                'uuid'       => 'dp.uuid',
                'key_name'   => 'dp.key_name',
                'value_type' => 'dp.value_type',
                'label'      => 'dp.label',
            ])
            ->where('dp.parent_uuid IS NULL');

        foreach ($dba->fetchAll($attachmentQuery, [], PDO::FETCH_ASSOC) as $row) {
            $row = DbUtil::normalizeRow($row);
            $this->attachments[$type][(int) $row['object_id']][$row['key_name']] = [
                'uuid'       => $row['uuid'],
                'key_name'   => $row['key_name'],
                'value_type' => $row['value_type'],
                'label'      => $row['label'],
                'required'   => $row['required'] === 'y',
            ];
        }

        if (static::followsCheckCommand($type)) {
            $this->loadType('command');
        }
    }

    /**
     * Load stored objects that are not loaded yet, with their imports
     *
     * @param string $type
     * @param int[]  $ids
     *
     * @return void
     */
    protected function loadObjects(string $type, array $ids): void
    {
        $this->loadType($type);

        $missing = [];
        foreach ($ids as $id) {
            if (! isset($this->objects[$type][(int) $id])) {
                $missing[(int) $id] = (int) $id;
            }
        }

        $dba = $this->db->getDbAdapter();
        foreach (array_chunk(array_values($missing), static::CHUNK_SIZE) as $chunk) {
            $query = $dba->select()
                ->from(['o' => 'icinga_' . $type], $this->objectColumns($type))
                ->where('o.id IN (?)', $chunk);
            foreach ($dba->fetchAll($query, [], PDO::FETCH_ASSOC) as $row) {
                $this->addObject($type, $row);
            }

            $this->addParents(
                $type,
                $dba->fetchAll(
                    $this->parentQuery($type)->where("i.{$type}_id IN (?)", $chunk),
                    [],
                    PDO::FETCH_ASSOC
                )
            );
        }
    }

    /**
     * Get the columns to load for an object of the given type
     *
     * @param string $type
     *
     * @return array<string, string>
     */
    protected function objectColumns(string $type): array
    {
        $columns = ['id' => 'o.id', 'name' => 'o.object_name', 'object_type' => 'o.object_type'];
        if (static::followsCheckCommand($type)) {
            $columns['command'] = 'o.check_command_id';
        }

        return $columns;
    }

    /**
     * Create a query for the imports of the given type, in import order
     *
     * @param string $type
     *
     * @return \Zend_Db_Select
     */
    protected function parentQuery(string $type)
    {
        return $this->db->getDbAdapter()->select()
            ->from(['i' => 'icinga_' . $type . '_inheritance'], [
                'id'        => "i.{$type}_id",
                'parent_id' => "i.parent_{$type}_id",
            ])
            ->order("i.{$type}_id")
            ->order('i.weight');
    }

    /**
     * Remember a loaded object
     *
     * @param string               $type
     * @param array<string, mixed> $row
     *
     * @return void
     */
    protected function addObject(string $type, array $row): void
    {
        $this->objects[$type][(int) $row['id']] = [
            'name'     => $row['name'],
            'template' => $row['object_type'] === 'template',
            'command'  => isset($row['command']) ? (int) $row['command'] : null,
        ];
        $this->parents[$type][(int) $row['id']] ??= [];
    }

    /**
     * Remember loaded imports
     *
     * @param string                          $type
     * @param array<int, array<string, mixed>> $rows Rows ordered by object id and weight
     *
     * @return void
     */
    protected function addParents(string $type, array $rows): void
    {
        foreach ($rows as $row) {
            $this->parents[$type][(int) $row['id']][] = (int) $row['parent_id'];
        }
    }

    /**
     * Create the source for a loaded object
     *
     * @param string                     $type
     * @param int                        $id
     * @param ?CustomPropertySourceKind $kind Defaults to Template, or Command for a plain command
     *
     * @return CustomPropertySource
     */
    protected function createSource(string $type, int $id, ?CustomPropertySourceKind $kind = null): CustomPropertySource
    {
        $object = $this->objects[$type][$id] ?? ['name' => '', 'template' => false];
        $kind ??= $type === 'command' && ! $object['template']
            ? CustomPropertySourceKind::Command
            : CustomPropertySourceKind::Template;

        return new CustomPropertySource(
            $kind,
            $type,
            $id,
            $object['name'],
            $object['template'],
            $this->attachments[$type][$id] ?? []
        );
    }

    /**
     * List the sources of a loaded object as pairs of type and id
     *
     * @param string $type
     * @param int    $id
     *
     * @return array<int, array{0: string, 1: int}>
     */
    protected function listStoredSources(string $type, int $id): array
    {
        $sources = [[$type, $id]];
        foreach ($this->listAncestorIds($type, $this->getParentIds($type, $id)) as $ancestorId) {
            $sources[] = [$type, $ancestorId];
        }

        if (static::followsCheckCommand($type)) {
            $commandId = $this->getEffectiveCommandId($type, $id);
            if ($commandId !== null) {
                foreach ($this->listCommandChain($commandId) as $chainId) {
                    $sources[] = ['command', $chainId];
                }
            }
        }

        return $sources;
    }

    /**
     * Get the parent ids of a loaded object, pending ones first
     *
     * @param string $type
     * @param int    $id
     *
     * @return int[]
     */
    protected function getParentIds(string $type, int $id): array
    {
        return $this->pendingParents[$type][$id] ?? $this->parents[$type][$id] ?? [];
    }

    /**
     * Get the ids of the objects the given object imports by name
     *
     * @param IcingaObject $object
     *
     * @return int[]
     */
    protected function getObjectParentIds(IcingaObject $object): array
    {
        if (! $object->supportsImports()) {
            return [];
        }

        $type = $object->getShortTableName();
        $parentIds = [];
        foreach ($object->imports()->listImportNames() as $name) {
            if (isset($this->importableIds[$type][$name])) {
                $parentIds[] = $this->importableIds[$type][$name];
            }
        }

        return $parentIds;
    }

    /**
     * Get the own check command id of the given object, which may not be stored yet
     *
     * @param IcingaObject $object
     *
     * @return ?int
     */
    protected function getObjectCommandId(IcingaObject $object): ?int
    {
        // A sync or a REST request sets the command by name, look it up without loading it
        $name = $object->getUnresolvedRelated('check_command');
        if ($name !== null) {
            return $this->importableIds['command'][$name] ?? null;
        }

        $id = $object->get('check_command_id');

        return $id === null ? null : (int) $id;
    }

    /**
     * List the ancestors of an object with the given parents, in Director's ancestor order
     *
     * Each parent's own ancestors come before the parent. An ancestor reached more
     * than once keeps the position of its last occurrence, as in TemplateTree.
     *
     * @param string $type
     * @param int[]  $parentIds
     *
     * @return int[]
     *
     * @throws NestingError When the imports form a loop
     */
    protected function listAncestorIds(string $type, array $parentIds): array
    {
        $ancestors = [];
        foreach ($parentIds as $parentId) {
            $this->collectAncestors($type, $parentId, $ancestors, []);
        }

        return array_keys($ancestors);
    }

    /**
     * Add an object and its ancestors to the given list
     *
     * @param string            $type
     * @param int               $id
     * @param array<int, true>  $ancestors
     * @param array<int, true>  $path      The objects on the way to this one, to detect loops
     *
     * @return void
     *
     * @throws NestingError When the imports form a loop
     */
    protected function collectAncestors(string $type, int $id, array &$ancestors, array $path): void
    {
        $this->assertNoLoop($type, $id, $path);
        $path[$id] = true;

        foreach ($this->getParentIds($type, $id) as $parentId) {
            $this->collectAncestors($type, $parentId, $ancestors, $path);
        }

        unset($ancestors[$id]);
        $ancestors[$id] = true;
    }

    /**
     * Get the effective check command id of a loaded host or service
     *
     * @param string           $type
     * @param int              $id
     * @param array<int, true> $path The objects on the way to this one, to detect loops
     *
     * @return ?int
     *
     * @throws NestingError When the imports form a loop
     */
    protected function getEffectiveCommandId(string $type, int $id, array $path = []): ?int
    {
        if (array_key_exists($id, $this->effectiveCommands[$type] ?? [])) {
            return $this->effectiveCommands[$type][$id];
        }

        $this->assertNoLoop($type, $id, $path);
        $path[$id] = true;

        if (array_key_exists($id, $this->pendingCommands[$type] ?? [])) {
            $commandId = $this->pendingCommands[$type][$id];
        } else {
            $commandId = $this->objects[$type][$id]['command'] ?? null;
        }

        if ($commandId === null) {
            foreach (array_reverse($this->getParentIds($type, $id)) as $parentId) {
                $commandId = $this->getEffectiveCommandId($type, $parentId, $path);
                if ($commandId !== null) {
                    break;
                }
            }
        }

        return $this->effectiveCommands[$type][$id] = $commandId;
    }

    /**
     * List a command and every command it imports, directly or indirectly
     *
     * @param int $commandId
     *
     * @return int[]
     */
    protected function listCommandChain(int $commandId): array
    {
        $chain = [$commandId];
        foreach ($this->listAncestorIds('command', $this->getParentIds('command', $commandId)) as $ancestorId) {
            if ($ancestorId !== $commandId) {
                $chain[] = $ancestorId;
            }
        }

        return $chain;
    }

    /**
     * Make sure an object is not already on the way to itself
     *
     * @param string           $type
     * @param int              $id
     * @param array<int, true> $path
     *
     * @return void
     *
     * @throws NestingError When the object is on the path
     */
    protected function assertNoLoop(string $type, int $id, array $path): void
    {
        if (! isset($path[$id])) {
            return;
        }

        $names = [];
        foreach (array_merge(array_keys($path), [$id]) as $pathId) {
            $names[] = $this->objects[$type][$pathId]['name'] ?? "id=$pathId";
        }

        throw new NestingError('Loop detected: %s', implode(' -> ', $names));
    }
}
