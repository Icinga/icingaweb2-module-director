<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\ObjectCustomPropertySources;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaUser;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class ObjectCustomPropertySourcesTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';
    private const GENERIC_TEMPLATE = self::PREFIX . 'generic-host';
    private const LINUX_TEMPLATE = self::PREFIX . 'linux-host';
    private const WEB_TEMPLATE = self::PREFIX . 'linux-web-template';
    private const MONITORED_TEMPLATE = self::PREFIX . 'monitored-host';
    private const INHERITING_HOST = self::PREFIX . 'inheriting-host';
    private const EXTERNAL_COMMAND = self::PREFIX . 'ping4';
    private const COMMAND_TEMPLATE = self::PREFIX . 'ping-base';
    private const CHILD_COMMAND = self::PREFIX . 'ping-child';
    private const USER_NAME = self::PREFIX . 'sources-user';
    private const WRTA_KEY = self::PREFIX . 'ping_wrta';
    private const CPL_KEY = self::PREFIX . 'ping_cpl';

    public function testHostSourcesListOwnFirstThenTemplatesInImportOrderThenCommand(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $host = IcingaHost::load(self::WEB_TEMPLATE, $this->getDb());

        $sources = ObjectCustomPropertySources::for($host, $this->getDb());

        $this->assertSame([
            'own:' . self::WEB_TEMPLATE,
            'template:' . self::GENERIC_TEMPLATE,
            'template:' . self::LINUX_TEMPLATE,
            'command:' . self::EXTERNAL_COMMAND,
        ], $this->summarize($sources));
        $this->assertSame(['host', 'host', 'host', 'command'], array_column($sources, 'type'));
    }

    public function testOwnSourceCarriesTheUuidOfTheObject(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $host = IcingaHost::load(self::WEB_TEMPLATE, $this->getDb());

        $sources = ObjectCustomPropertySources::for($host, $this->getDb());

        $this->assertSame($host->get('uuid'), $sources[0]['uuid']);
    }

    public function testCommandInheritedThroughATemplateIsFollowedWithItsOwnTemplates(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $host = IcingaHost::load(self::INHERITING_HOST, $this->getDb());

        $sources = ObjectCustomPropertySources::for($host, $this->getDb());

        $this->assertSame([
            'template:' . self::MONITORED_TEMPLATE,
            'command:' . self::COMMAND_TEMPLATE,
            'command:' . self::CHILD_COMMAND,
        ], $this->summarize($sources));
    }

    public function testObjectThatCannotAttachPropertiesHasNoOwnSource(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $host = IcingaHost::load(self::INHERITING_HOST, $this->getDb());

        $sources = ObjectCustomPropertySources::for($host, $this->getDb());

        $this->assertNotContains(ObjectCustomPropertySources::OWN, array_column($sources, 'kind'));
    }

    public function testCommandHasItsOwnSourceBeforeItsTemplates(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $command = IcingaCommand::load(self::CHILD_COMMAND, $this->getDb());

        $sources = ObjectCustomPropertySources::for($command, $this->getDb());

        $this->assertSame([
            'own:' . self::CHILD_COMMAND,
            'template:' . self::COMMAND_TEMPLATE,
        ], $this->summarize($sources));
        $this->assertSame(['command', 'command'], array_column($sources, 'type'));
    }

    public function testSourcesCarryTheObjectTypeOfTheirObject(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $command = IcingaCommand::load(self::CHILD_COMMAND, $this->getDb());

        $sources = ObjectCustomPropertySources::for($command, $this->getDb());

        $this->assertSame(['object', 'template'], array_column($sources, 'object_type'));
    }

    public function testObjectWithoutACommandRelationHasNoCommandSources(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $user = IcingaUser::load(self::USER_NAME, $this->getDb());

        $sources = ObjectCustomPropertySources::for($user, $this->getDb());

        $this->assertSame(['own:' . self::USER_NAME], $this->summarize($sources));
    }

    public function testCommandPropertiesAreListedByKeyForAnExternalCommand(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $host = IcingaHost::load(self::WEB_TEMPLATE, $this->getDb());

        $properties = ObjectCustomPropertySources::commandPropertiesByKey($host, $this->getDb());

        $this->assertSame([self::WRTA_KEY], array_keys($properties));
        $this->assertSame('string', $properties[self::WRTA_KEY]['value_type']);
        $this->assertSame('Warning round trip', $properties[self::WRTA_KEY]['label']);
    }

    public function testCommandPropertiesIncludeTheOnesOfTheCommandTemplates(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $host = IcingaHost::load(self::INHERITING_HOST, $this->getDb());

        $properties = ObjectCustomPropertySources::commandPropertiesByKey($host, $this->getDb());

        $this->assertSame([self::CPL_KEY], array_keys($properties));
    }

    public function testCommandPropertiesAreEmptyWithoutACommand(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $host = IcingaHost::load(self::GENERIC_TEMPLATE, $this->getDb());

        $this->assertSame([], ObjectCustomPropertySources::commandPropertiesByKey($host, $this->getDb()));
    }

    public function testCommandIdIsFoundForAnOwnCommandAndForOneFromATemplate(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $db = $this->getDb();

        $this->assertSame(
            (int) IcingaCommand::load(self::EXTERNAL_COMMAND, $db)->get('id'),
            ObjectCustomPropertySources::resolveCommandId(IcingaHost::load(self::WEB_TEMPLATE, $db))
        );
        $this->assertSame(
            (int) IcingaCommand::load(self::CHILD_COMMAND, $db)->get('id'),
            ObjectCustomPropertySources::resolveCommandId(IcingaHost::load(self::INHERITING_HOST, $db))
        );
    }

    public function testCommandIdIsNullWithoutACommandOrACommandRelation(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $db = $this->getDb();

        $this->assertNull(ObjectCustomPropertySources::resolveCommandId(IcingaHost::load(self::GENERIC_TEMPLATE, $db)));
        $this->assertNull(ObjectCustomPropertySources::resolveCommandId(IcingaUser::load(self::USER_NAME, $db)));
    }

    public function testCommandPropertiesByCommandIdMatchTheOnesFoundThroughAnObject(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createFixtures();
        $db = $this->getDb();
        $host = IcingaHost::load(self::INHERITING_HOST, $db);

        $byId = ObjectCustomPropertySources::commandPropertiesByCommandId(
            (int) IcingaCommand::load(self::CHILD_COMMAND, $db)->get('id'),
            $db
        );

        $this->assertSame([self::CPL_KEY], array_keys($byId));
        $this->assertEquals(ObjectCustomPropertySources::commandPropertiesByKey($host, $db), $byId);
    }

    public function testCommandPropertiesByCommandIdFailForAnUnknownCommand(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->expectException(\RuntimeException::class);
        ObjectCustomPropertySources::commandPropertiesByCommandId(2147483000, $this->getDb());
    }

    public function testPickSourcePrefersTheObjectsOwnSource(): void
    {
        $source = ObjectCustomPropertySources::pickSource([
            $this->source('template', 'generic-host'),
            $this->source('own', 'linux-web-template'),
            $this->source('template', 'linux-host'),
        ], 'generic-host');

        $this->assertSame('linux-web-template', $source['name']);
    }

    public function testPickSourcePrefersTheTemplateThatProvidesTheValue(): void
    {
        $source = ObjectCustomPropertySources::pickSource([
            $this->source('template', 'generic-host'),
            $this->source('template', 'linux-host'),
        ], 'generic-host');

        $this->assertSame('generic-host', $source['name']);
    }

    public function testPickSourceFallsBackToTheLastImport(): void
    {
        $source = ObjectCustomPropertySources::pickSource([
            $this->source('template', 'generic-host'),
            $this->source('template', 'linux-host'),
        ], null);

        $this->assertSame('linux-host', $source['name']);
    }

    public function testPickSourceIgnoresAValueTemplateWithoutTheKey(): void
    {
        $source = ObjectCustomPropertySources::pickSource([
            $this->source('template', 'generic-host'),
            $this->source('template', 'linux-host'),
        ], 'some-other-template');

        $this->assertSame('linux-host', $source['name']);
    }

    public function testPickCommandSourceReturnsTheCommandAfterItsTemplates(): void
    {
        $source = ObjectCustomPropertySources::pickCommandSource([
            $this->source('template', 'linux-host'),
            $this->source('command', 'ping-base'),
            $this->source('command', 'ping-child'),
        ]);

        $this->assertSame('ping-child', $source['name']);
    }

    public function testPickCommandSourceIsNullWithoutACommand(): void
    {
        $source = ObjectCustomPropertySources::pickCommandSource([
            $this->source('own', 'linux-web-template'),
            $this->source('template', 'linux-host'),
        ]);

        $this->assertNull($source);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            foreach (
                [
                    self::INHERITING_HOST,
                    self::MONITORED_TEMPLATE,
                    self::WEB_TEMPLATE,
                    self::LINUX_TEMPLATE,
                    self::GENERIC_TEMPLATE,
                ] as $name
            ) {
                if (IcingaHost::exists($name, $db)) {
                    IcingaHost::load($name, $db)->delete();
                }
            }

            foreach ([self::CHILD_COMMAND, self::COMMAND_TEMPLATE, self::EXTERNAL_COMMAND] as $name) {
                if (IcingaCommand::exists($name, $db)) {
                    IcingaCommand::load($name, $db)->delete();
                }
            }

            if (IcingaUser::exists(self::USER_NAME, $db)) {
                IcingaUser::load(self::USER_NAME, $db)->delete();
            }

            $dba = $db->getDbAdapter();
            $dba->delete('director_property', $dba->quoteInto('key_name IN (?)', [self::WRTA_KEY, self::CPL_KEY]));
        }

        // The tree remembers ids from the objects deleted above
        IcingaTemplateRepository::clear();

        parent::tearDown();
    }

    /**
     * Build the small world the tests share
     *
     * The web template imports two templates and uses an external command. The inheriting host
     * only gets its command through a template, and that command imports a command template.
     */
    private function createFixtures(): void
    {
        $this->tearDown();
        $db = $this->getDb();

        $external = IcingaCommand::create([
            'object_name' => self::EXTERNAL_COMMAND,
            'object_type' => 'external_object',
            'command'     => 'ping4',
        ], $db);
        $external->store();

        $base = IcingaCommand::create([
            'object_name' => self::COMMAND_TEMPLATE,
            'object_type' => 'template',
        ], $db);
        $base->store();

        $child = IcingaCommand::create([
            'object_name' => self::CHILD_COMMAND,
            'object_type' => 'object',
            'command'     => '/usr/lib/nagios/plugins/check_ping',
        ], $db);
        $child->set('imports', [self::COMMAND_TEMPLATE]);
        $child->store();

        $this->attach($external, $this->createProperty(self::WRTA_KEY, 'Warning round trip'));
        $this->attach($base, $this->createProperty(self::CPL_KEY, 'Critical packet loss'));

        $this->createHost(self::GENERIC_TEMPLATE, 'template');
        $this->createHost(self::LINUX_TEMPLATE, 'template');
        $this->createHost(self::WEB_TEMPLATE, 'template', [self::GENERIC_TEMPLATE, self::LINUX_TEMPLATE], [
            'check_command' => self::EXTERNAL_COMMAND,
        ]);
        $this->createHost(self::MONITORED_TEMPLATE, 'template', [], ['check_command' => self::CHILD_COMMAND]);
        $this->createHost(self::INHERITING_HOST, 'object', [self::MONITORED_TEMPLATE]);

        IcingaUser::create(['object_name' => self::USER_NAME, 'object_type' => 'template'], $db)->store();
    }

    /**
     * Create a host and store it
     *
     * @param string $name
     * @param string $objectType
     * @param string[] $imports The names of the templates to import
     * @param array $extra More properties to set
     *
     * @return void
     */
    private function createHost(string $name, string $objectType, array $imports = [], array $extra = []): void
    {
        $host = IcingaHost::create(array_merge([
            'object_name' => $name,
            'object_type' => $objectType,
        ], $extra), $this->getDb());
        if (! empty($imports)) {
            $host->set('imports', $imports);
        }

        $host->store();
    }

    /**
     * Create a custom property of type string
     *
     * @param string $key
     * @param string $label
     *
     * @return DirectorProperty
     */
    private function createProperty(string $key, string $label): DirectorProperty
    {
        $property = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => $key,
            'value_type' => 'string',
            'label'      => $label,
        ], $this->getDb());
        $property->store();

        return $property;
    }

    /**
     * Attach the given property to the given command
     *
     * @param IcingaCommand $command
     * @param DirectorProperty $property
     *
     * @return void
     */
    private function attach(IcingaCommand $command, DirectorProperty $property): void
    {
        $dba = $this->getDb()->getDbAdapter();
        $dba->insert('icinga_command_property', [
            'command_uuid'  => DbUtil::quoteBinaryCompat($command->get('uuid'), $dba),
            'property_uuid' => DbUtil::quoteBinaryCompat($property->get('uuid'), $dba),
            'required'      => 'n',
        ]);
    }

    /**
     * Describe every source as kind and name, like template:generic-host
     *
     * @param array $sources
     *
     * @return string[]
     */
    private function summarize(array $sources): array
    {
        return array_map(fn(array $source) => $source['kind'] . ':' . $source['name'], $sources);
    }

    /**
     * Build a source by hand for the tests that need no database
     *
     * @param string $kind
     * @param string $name
     *
     * @return array
     */
    private function source(string $kind, string $name): array
    {
        return ['kind' => $kind, 'name' => $name, 'type' => 'host', 'uuid' => $name];
    }
}
