<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Lib\CustomVariable;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Ramsey\Uuid\Uuid;

/**
 * Creates commands, hosts, services and custom properties for a test and removes them afterwards
 *
 * Every name gets the prefix "___TEST___cmdvar-". Call {@see removeFixtures()} in tearDown().
 */
trait CommandVariableFixture
{
    /** @var IcingaObject[] Created objects, in creation order */
    private array $fixtureObjects = [];

    /**
     * Get the database of the test
     *
     * @return Db
     */
    abstract protected static function getDb();

    /**
     * Prefix a fixture name
     *
     * @param string $name
     *
     * @return string
     */
    protected function fx(string $name): string
    {
        return '___TEST___cmdvar-' . $name;
    }

    /**
     * Create a root custom property
     *
     * @param string $name Unprefixed key name
     * @param string $type
     *
     * @return DirectorProperty
     */
    protected function createFixtureProperty(string $name, string $type = 'string'): DirectorProperty
    {
        $db = $this->getDb();
        $db->getDbAdapter()->delete('director_property', ['key_name = ?' => $this->fx($name)]);
        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => $this->fx($name),
            'value_type' => $type,
            'label'      => $name,
        ], $db);
        $property->store();

        return $property;
    }

    /**
     * Create a command
     *
     * @param string             $name       Unprefixed object name
     * @param string             $objectType
     * @param string[]           $imports    Unprefixed names of imported commands
     * @param DirectorProperty[] $attach     Properties to attach
     *
     * @return IcingaCommand
     */
    protected function createFixtureCommand(
        string $name,
        string $objectType = 'object',
        array $imports = [],
        array $attach = []
    ): IcingaCommand {
        $command = IcingaCommand::create([
            'object_name' => $this->fx($name),
            'object_type' => $objectType,
            'command'     => '/usr/lib/nagios/plugins/check_dummy',
        ], $this->getDb());
        $command->setImports(array_map([$this, 'fx'], $imports));

        return $this->storeFixture($command, $attach);
    }

    /**
     * Create a host or host template
     *
     * @param string             $name         Unprefixed object name
     * @param string             $objectType
     * @param string[]           $imports      Unprefixed names of imported host templates
     * @param ?string            $checkCommand Unprefixed name of the own check command
     * @param DirectorProperty[] $attach       Properties to attach
     *
     * @return IcingaHost
     */
    protected function createFixtureHost(
        string $name,
        string $objectType = 'object',
        array $imports = [],
        ?string $checkCommand = null,
        array $attach = []
    ): IcingaHost {
        $host = IcingaHost::create([
            'object_name' => $this->fx($name),
            'object_type' => $objectType,
        ], $this->getDb());
        $host->setImports(array_map([$this, 'fx'], $imports));
        if ($checkCommand !== null) {
            $host->set('check_command', $this->fx($checkCommand));
        }

        return $this->storeFixture($host, $attach);
    }

    /**
     * Create a service, service template or service apply rule
     *
     * @param string             $name         Unprefixed object name
     * @param string             $objectType
     * @param string[]           $imports      Unprefixed names of imported service templates
     * @param ?string            $checkCommand Unprefixed name of the own check command
     * @param ?string            $host         Unprefixed name of the host of a service object
     * @param DirectorProperty[] $attach       Properties to attach
     *
     * @return IcingaService
     */
    protected function createFixtureService(
        string $name,
        string $objectType = 'object',
        array $imports = [],
        ?string $checkCommand = null,
        ?string $host = null,
        array $attach = []
    ): IcingaService {
        $properties = ['object_name' => $this->fx($name), 'object_type' => $objectType];
        if ($host !== null) {
            $properties['host'] = $this->fx($host);
        }

        if ($objectType === 'apply') {
            $properties['assign_filter'] = 'host.name="' . $this->fx('nothing') . '"';
        }

        $service = IcingaService::create($properties, $this->getDb());
        $service->setImports(array_map([$this, 'fx'], $imports));
        if ($checkCommand !== null) {
            $service->set('check_command', $this->fx($checkCommand));
        }

        return $this->storeFixture($service, $attach);
    }

    /**
     * Attach a property to an object
     *
     * @param IcingaObject     $object
     * @param DirectorProperty $property
     * @param bool             $required
     *
     * @return void
     */
    protected function attachFixtureProperty(
        IcingaObject $object,
        DirectorProperty $property,
        bool $required = false
    ): void {
        $type = $object->getShortTableName();
        $dba = $this->getDb()->getDbAdapter();
        $dba->insert('icinga_' . $type . '_property', [
            $type . '_uuid' => DbUtil::quoteBinaryCompat($object->get('uuid'), $dba),
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'required'      => $required ? 'y' : 'n',
        ]);
    }

    /**
     * Detach a property from an object
     *
     * @param IcingaObject     $object
     * @param DirectorProperty $property
     *
     * @return void
     */
    protected function detachFixtureProperty(IcingaObject $object, DirectorProperty $property): void
    {
        $type = $object->getShortTableName();
        $dba = $this->getDb()->getDbAdapter();
        $dba->delete(
            'icinga_' . $type . '_property',
            $dba->quoteInto($type . '_uuid = ?', DbUtil::quoteBinaryCompat($object->get('uuid'), $dba))
            . ' AND '
            . $dba->quoteInto('property_uuid = ?', DbUtil::quoteBinaryCompat($property->get('uuid'), $dba))
        );
    }

    /**
     * Store a value on a stored object, linked to the given property or plain
     *
     * @param IcingaObject      $object
     * @param string            $name     Unprefixed variable name
     * @param mixed             $value
     * @param ?DirectorProperty $property The property to link the value to, null for a plain value
     *
     * @return void
     */
    protected function storeFixtureValue(
        IcingaObject $object,
        string $name,
        mixed $value,
        ?DirectorProperty $property = null
    ): void {
        $vars = $object->vars();
        $vars->set($this->fx($name), $value);
        if ($property !== null) {
            $vars->registerVarUuid($this->fx($name), Uuid::fromBytes($property->get('uuid')));
        }

        $vars->storeToDb($object);
    }

    /**
     * Get the stored link of a value
     *
     * @param IcingaObject $object
     * @param string       $name   Unprefixed variable name
     *
     * @return string|false|null The binary property uuid, null for a plain value, false for no value
     */
    protected function fetchFixtureLink(IcingaObject $object, string $name)
    {
        $type = $object->getShortTableName();
        $dba = $this->getDb()->getDbAdapter();
        $row = $dba->fetchRow(
            $dba->select()
                ->from('icinga_' . $type . '_var', ['property_uuid'])
                ->where($type . '_id = ?', (int) $object->get('id'))
                ->where('varname = ?', $this->fx($name)),
            [],
            \PDO::FETCH_ASSOC
        );

        if ($row === false) {
            return false;
        }

        return DbUtil::binaryResult($row['property_uuid']);
    }

    /**
     * Remove every object and property the fixture created
     *
     * @return void
     */
    protected function removeFixtures(): void
    {
        if (! $this->hasDb()) {
            return;
        }

        IcingaTemplateRepository::clear();
        foreach (array_reverse($this->fixtureObjects) as $object) {
            $class = $object::class;
            try {
                $class::loadWithAutoIncId((int) $object->get('id'), $this->getDb())->delete();
            } catch (NotFoundError $e) {
                // the test removed it already
            }
        }

        $this->fixtureObjects = [];
        $this->getDb()->getDbAdapter()->delete('director_property', ['key_name LIKE ?' => $this->fx('%')]);
        IcingaTemplateRepository::clear();
    }

    /**
     * Store a new fixture object and attach properties to it
     *
     * @template T of IcingaObject
     *
     * @param T                  $object
     * @param DirectorProperty[] $attach
     *
     * @return T
     */
    private function storeFixture(IcingaObject $object, array $attach)
    {
        $object->store();
        $this->fixtureObjects[] = $object;
        foreach ($attach as $property) {
            $this->attachFixtureProperty($object, $property);
        }

        IcingaTemplateRepository::clear();

        return $object;
    }
}
