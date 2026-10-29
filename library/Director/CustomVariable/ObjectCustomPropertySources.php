<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CustomVariable;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use PDO;
use RuntimeException;

/**
 * Lists the places an object can get custom properties from
 *
 * Host and command ids come from different tables, so every entry says which
 * type it is. Never mix the two kinds of ids in one list.
 *
 * @phpstan-type PropertySource array{
 *     kind: string,
 *     name: string,
 *     type: string,
 *     uuid: string,
 *     object_type: string
 * }
 * @phpstan-type CommandProperty array{
 *     key_name: string,
 *     uuid: string,
 *     value_type: string,
 *     label: ?string,
 *     category: ?string
 * }
 */
class ObjectCustomPropertySources
{
    /** @var string The object itself, shown first and without a heading */
    public const OWN = 'own';

    /** @var string A template the object imports */
    public const TEMPLATE = 'template';

    /** @var string The command the object uses, or a template of that command */
    public const COMMAND = 'command';

    /**
     * Get every place the given object can get custom properties from
     *
     * The list is in the order the sources are shown. The object itself comes first,
     * but only if it can attach properties. Then come the imported templates, and
     * the command with its templates comes last.
     *
     * @param IcingaObject $object
     * @param Db $db
     *
     * @return array<int, PropertySource>
     */
    public static function for(IcingaObject $object, Db $db): array
    {
        return array_merge(
            self::objectSources($object, $db),
            self::commandSources($object, $db)
        );
    }

    /**
     * Get the properties attached to the command of the given object, by key name
     *
     * The templates of the command are included.
     *
     * @param IcingaObject $object
     * @param Db $db
     *
     * @return array<string, CommandProperty>
     */
    public static function commandPropertiesByKey(IcingaObject $object, Db $db): array
    {
        $commandId = self::resolveCommandId($object);

        return $commandId === null ? [] : self::commandPropertiesByCommandId($commandId, $db);
    }

    /**
     * Get the id of the check command the object uses, also when it only gets it through a template
     *
     * Only the id is looked up, so a batch can group objects by command before loading any.
     *
     * @param IcingaObject $object
     *
     * @return ?int
     */
    public static function resolveCommandId(IcingaObject $object): ?int
    {
        if (! $object->hasRelation('check_command')) {
            return null;
        }

        $commandId = $object->getSingleResolvedProperty('check_command_id');

        return $commandId ? (int) $commandId : null;
    }

    /**
     * Get the properties attached to the given command and its templates, by key name
     *
     * @param int $commandId
     * @param Db $db
     *
     * @return array<string, CommandProperty>
     */
    public static function commandPropertiesByCommandId(int $commandId, Db $db): array
    {
        $dba = $db->getDbAdapter();
        $uuids = [];
        foreach (self::commandSourcesOf(self::loadCommand($commandId, $db), $db) as $source) {
            $uuids[] = DbUtil::quoteBinaryCompat($source['uuid'], $dba);
        }

        if (empty($uuids)) {
            return [];
        }

        $query = $dba->select()
            ->from(['iop' => 'icinga_command_property'], [])
            ->join(['dp' => 'director_property'], 'dp.uuid = iop.property_uuid', [
                'key_name'   => 'dp.key_name',
                'uuid'       => 'dp.uuid',
                'value_type' => 'dp.value_type',
                'label'      => 'dp.label'
            ])
            ->joinLeft(['cpc' => 'director_datafield_category'], 'dp.category_id = cpc.id', [
                'category' => 'cpc.category_name'
            ])
            ->where('iop.command_uuid IN (?)', $uuids)
            ->where('dp.parent_uuid IS NULL');

        $result = [];
        foreach ($dba->fetchAll($query, [], PDO::FETCH_ASSOC) as $row) {
            $row = DbUtil::normalizeRow($row);
            $result[$row['key_name']] = $row;
        }

        return $result;
    }

    /**
     * Pick the source a property is shown under when several sources attach it
     *
     * The object itself comes first, then the template that provides the value,
     * then the template imported last.
     *
     * @param array<int, PropertySource> $sources
     *        The sources that attach the property, in import order
     * @param ?string $valueTemplateName The template that provides the value, if any
     *
     * @return PropertySource
     */
    public static function pickSource(array $sources, ?string $valueTemplateName): array
    {
        assert(! empty($sources));

        foreach ($sources as $source) {
            if ($source['kind'] === self::OWN) {
                return $source;
            }
        }

        if ($valueTemplateName !== null) {
            foreach ($sources as $source) {
                if ($source['name'] === $valueTemplateName) {
                    return $source;
                }
            }
        }

        // A later import overrides an earlier one
        return end($sources);
    }

    /**
     * Get the command the object uses
     *
     * The templates of the command only help to find its properties. The rows
     * of all of them are shown together under the command itself, which is
     * the last command in the list.
     *
     * @param array<PropertySource> $sources
     *
     * @return ?PropertySource
     */
    public static function pickCommandSource(array $sources): ?array
    {
        foreach (array_reverse($sources) as $source) {
            if ($source['kind'] === self::COMMAND) {
                return $source;
            }
        }

        return null;
    }

    /**
     * Get the object itself if it can attach properties, followed by its imported templates
     *
     * @param IcingaObject $object
     * @param Db $db
     *
     * @return array<int, PropertySource>
     */
    private static function objectSources(IcingaObject $object, Db $db): array
    {
        $sources = [];

        // Only templates and commands can attach properties; a normal host has nothing of its own to show
        if ($object->hasBeenLoadedFromDb() && $object->definesCustomProperties()) {
            $sources[] = self::describe(self::OWN, $object);
        }

        // The repository keeps the templates it loaded, value resolution has most of them already
        $templates = IcingaTemplateRepository::instanceByObject($object, $db)->getTemplatesFor($object, true);
        foreach ($templates as $template) {
            $sources[] = self::describe(self::TEMPLATE, $template);
        }

        return $sources;
    }

    /**
     * Get the command of the given object, after the templates it imports
     *
     * @param IcingaObject $object
     * @param Db $db
     *
     * @return array<int, PropertySource>
     */
    private static function commandSources(IcingaObject $object, Db $db): array
    {
        $commandId = self::resolveCommandId($object);
        if ($commandId === null) {
            return [];
        }

        return self::commandSourcesOf(self::loadCommand($commandId, $db), $db);
    }

    /**
     * Get the command and the templates it imports, templates first
     *
     * @param IcingaCommand $command
     * @param Db $db
     *
     * @return array<int, PropertySource>
     */
    private static function commandSourcesOf(IcingaCommand $command, Db $db): array
    {
        $sources = [];
        $templates = IcingaTemplateRepository::instanceByObject($command, $db)->getTemplatesFor($command, true);
        foreach ($templates as $template) {
            $sources[] = self::describe(self::COMMAND, $template);
        }

        $sources[] = self::describe(self::COMMAND, $command);

        return $sources;
    }

    /**
     * Load the command with the given id
     *
     * @param int $commandId
     * @param Db $db
     *
     * @return IcingaCommand
     *
     * @throws RuntimeException When there is no such command
     */
    private static function loadCommand(int $commandId, Db $db): IcingaCommand
    {
        try {
            return IcingaCommand::loadWithAutoIncId($commandId, $db);
        } catch (NotFoundError $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Describe one place the properties can come from
     *
     * @param string $kind One of the constants of this class
     * @param IcingaObject $source
     *
     * @return PropertySource
     */
    private static function describe(string $kind, IcingaObject $source): array
    {
        return [
            'kind' => $kind,
            'name' => $source->getObjectName(),
            'type' => $source->getShortTableName(),
            'uuid' => $source->get('uuid'),
            'object_type' => $source->get('object_type')
        ];
    }
}
