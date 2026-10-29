<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Controller;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Controllers\HostController;
use Icinga\Module\Director\CustomVariable\ObjectCustomPropertySources;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\CustomVariablesForm;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Controller\ActionController;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Web\UrlParams;
use Ramsey\Uuid\Uuid;
use ReflectionClass;
use ReflectionProperty;

class ObjectControllerCustomPropertiesTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';
    private const GENERIC_TEMPLATE = self::PREFIX . 'generic-host';
    private const LINUX_TEMPLATE = self::PREFIX . 'linux-host';
    private const WEB_TEMPLATE = self::PREFIX . 'web-servers';
    private const WEB_HOST = self::PREFIX . 'web01.example.com';
    private const COMMAND = self::PREFIX . 'ping4';
    private const COMMAND_BASE = self::PREFIX . 'ping-base';
    private const CHILD_COMMAND = self::PREFIX . 'ping-child';
    private const DATABASE_HOST = self::PREFIX . 'db01.example.com';
    private const SERVICE = self::PREFIX . 'ping';
    private const SERVICE_SET = self::PREFIX . 'basic-checks';
    private const OS_KEY = self::PREFIX . 'os';
    private const WRTA_KEY = self::PREFIX . 'ping_wrta';
    private const CPL_KEY = self::PREFIX . 'ping_cpl';
    private const LOCATION_KEY = self::PREFIX . 'location';

    public function testDirectAttachmentWinsOverAnAncestorRequiringIt(): void
    {
        $controller = $this->newController();

        $property = $this->callResolve($controller, [
            ['uuid' => 'object', 'required' => false],
            ['uuid' => 'ancestor', 'required' => true],
        ], 'object');

        $this->assertTrue($property['allow_removal']);
        $this->assertFalse(
            $property['required'],
            'a direct attachment on the object itself must decide required, not an ancestor'
        );
    }

    public function testAnyAncestorRequiringItIsEnoughWithoutADirectAttachment(): void
    {
        $controller = $this->newController();

        $property = $this->callResolve($controller, [
            ['uuid' => 'ancestor-a', 'required' => false],
            ['uuid' => 'ancestor-b', 'required' => true],
        ], 'object');

        $this->assertFalse($property['allow_removal']);
        $this->assertTrue(
            $property['required'],
            'one ancestor requiring it is enough, an object cannot drop a requirement it inherited'
        );
    }

    public function testNeitherRequiredNorRemovableWithoutAnyAttachment(): void
    {
        $controller = $this->newController();

        $property = $this->callResolve($controller, [], 'object');

        $this->assertFalse($property['allow_removal']);
        $this->assertFalse($property['required']);
    }

    public function testAddingAVariableToAPlainHostIsRejected(): void
    {
        $host = IcingaHost::create(['object_name' => 'web01.example.com', 'object_type' => 'object']);

        $this->expectException(NotFoundError::class);

        self::callMethod($this->newController(), 'assertDefinesCustomProperties', [$host]);
    }

    public function testAddingAVariableToATemplateIsAllowed(): void
    {
        $host = IcingaHost::create(['object_name' => 'web-servers', 'object_type' => 'template']);

        self::callMethod($this->newController(), 'assertDefinesCustomProperties', [$host]);

        $this->addToAssertionCount(1);
    }

    public function testAddingAVariableToAnExternalCommandIsAllowed(): void
    {
        $command = IcingaCommand::create(['object_name' => 'ping4', 'object_type' => 'external_object']);

        self::callMethod($this->newController(), 'assertDefinesCustomProperties', [$command]);

        $this->addToAssertionCount(1);
    }

    public function testCommandVarGetsTheCommandSection(): void
    {
        $this->createFixtures();

        $rows = $this->loadRows(self::WEB_TEMPLATE);

        $this->assertSame(ObjectCustomPropertySources::COMMAND, $rows[self::WRTA_KEY]['section']['kind']);
        $this->assertSame(self::COMMAND, $rows[self::WRTA_KEY]['section']['name']);
        $this->assertFalse($rows[self::WRTA_KEY]['allow_removal']);
    }

    public function testVarsOfACommandAndItsTemplateShareOneSection(): void
    {
        $this->createCommandChainFixtures();

        $rows = $this->loadRows(self::DATABASE_HOST);

        $this->assertSame(ObjectCustomPropertySources::COMMAND, $rows[self::WRTA_KEY]['section']['kind']);
        $this->assertSame(self::CHILD_COMMAND, $rows[self::WRTA_KEY]['section']['name']);
        $this->assertSame(ObjectCustomPropertySources::COMMAND, $rows[self::CPL_KEY]['section']['kind']);
        $this->assertSame(
            self::CHILD_COMMAND,
            $rows[self::CPL_KEY]['section']['name'],
            'a variable only the command template attaches belongs under the command the host uses'
        );
        $this->assertSame($rows[self::WRTA_KEY]['section']['order'], $rows[self::CPL_KEY]['section']['order']);
        $this->assertFalse($rows[self::CPL_KEY]['allow_removal']);
    }

    public function testOverrideFormListsTheCommandVarWithTheStoredOverride(): void
    {
        $this->createFixtures();
        $db = $this->getDb();

        $service = IcingaService::create([
            'object_name'   => self::SERVICE,
            'object_type'   => 'object',
            'host_id'       => IcingaHost::load(self::WEB_TEMPLATE, $db)->get('id'),
            'check_command' => self::COMMAND,
        ], $db);
        $service->store($db);

        $host = IcingaHost::load(self::WEB_HOST, $db);
        $host->overrideServiceVars(self::SERVICE, (object) [self::WRTA_KEY => '200'])->store();

        // The form looks the host and the service name up from the request
        $controller = $this->controllerWithDb();
        (new ReflectionProperty(ObjectController::class, 'object'))->setValue($controller, $host);
        (new ReflectionProperty(ActionController::class, 'params'))
            ->setValue($controller, (new UrlParams())->set('service', self::SERVICE));

        $rows = self::callMethod($controller, 'getObjectCustomProperties', [$service, true, [], []]);

        $this->assertSame(ObjectCustomPropertySources::COMMAND, $rows[self::WRTA_KEY]['section']['kind']);
        $this->assertSame(self::COMMAND, $rows[self::WRTA_KEY]['section']['name']);
        $this->assertSame('200', $rows[self::WRTA_KEY]['value']);
    }

    public function testOverrideFormListsTheCommandVarOfAServiceInAServiceSet(): void
    {
        $this->createFixtures();
        $db = $this->getDb();

        IcingaServiceSet::create([
            'object_name' => self::SERVICE_SET,
            'object_type' => 'template',
        ], $db)->store();
        $service = IcingaService::create([
            'object_name'   => self::SERVICE,
            'object_type'   => 'object',
            'service_set'   => self::SERVICE_SET,
            'check_command' => self::COMMAND,
        ], $db);
        $service->store($db);

        $host = IcingaHost::load(self::WEB_HOST, $db);
        $host->overrideServiceVars(self::SERVICE, (object) [self::WRTA_KEY => '300'])->store();

        $controller = $this->controllerWithDb();
        (new ReflectionProperty(ObjectController::class, 'object'))->setValue($controller, $host);
        (new ReflectionProperty(ActionController::class, 'params'))
            ->setValue($controller, (new UrlParams())->set('service', self::SERVICE));

        $rows = self::callMethod($controller, 'getObjectCustomProperties', [$service, true, [], []]);

        $this->assertSame(ObjectCustomPropertySources::COMMAND, $rows[self::WRTA_KEY]['section']['kind']);
        $this->assertSame('300', $rows[self::WRTA_KEY]['value']);
    }

    public function testCommandRequiredFlagIsIgnored(): void
    {
        $this->createFixtures();

        $rows = $this->loadRows(self::WEB_TEMPLATE);

        $this->assertFalse($rows[self::CPL_KEY]['required']);
    }

    public function testVarOnTwoTemplatesIsListedOnceUnderTheLastImport(): void
    {
        $this->createFixtures();

        $rows = $this->loadRows(self::WEB_TEMPLATE);

        $this->assertCount(1, array_filter(array_keys($rows), fn($key) => $key === self::OS_KEY));
        $this->assertSame(self::LINUX_TEMPLATE, $rows[self::OS_KEY]['section']['name']);
    }

    public function testSectionFollowsTheTemplateThatGivesTheValue(): void
    {
        $this->createFixtures();

        $generic = IcingaHost::load(self::GENERIC_TEMPLATE, $this->getDb());
        $generic->vars()->set(self::OS_KEY, 'Linux');
        $generic->store();
        // The repository still holds the template as it was before the value was set
        IcingaTemplateRepository::clear();
        $rows = $this->loadRows(self::WEB_TEMPLATE);

        $this->assertSame(self::GENERIC_TEMPLATE, $rows[self::OS_KEY]['section']['name']);
    }

    public function testOwnAttachmentWinsOverTemplates(): void
    {
        $this->createFixtures();

        $this->attach('host', self::WEB_TEMPLATE, self::OS_KEY);
        $rows = $this->loadRows(self::WEB_TEMPLATE);

        $this->assertSame(ObjectCustomPropertySources::OWN, $rows[self::OS_KEY]['section']['kind']);
        $this->assertTrue($rows[self::OS_KEY]['allow_removal']);
    }

    public function testRequiredStaysSetWhenTheRequiringTemplateIsNotShown(): void
    {
        $this->createFixtures();

        $rows = $this->loadRows(self::WEB_TEMPLATE);

        $this->assertSame(self::LINUX_TEMPLATE, $rows[self::OS_KEY]['section']['name']);
        $this->assertTrue($rows[self::OS_KEY]['required']);
    }

    public function testSectionsFollowTheOrderOfTheSourceList(): void
    {
        $this->createFixtures();
        $this->attach('host', self::WEB_TEMPLATE, self::CPL_KEY);

        $rows = $this->loadRows(self::WEB_TEMPLATE);

        $this->assertLessThan($rows[self::OS_KEY]['section']['order'], $rows[self::CPL_KEY]['section']['order']);
        $this->assertLessThan($rows[self::WRTA_KEY]['section']['order'], $rows[self::OS_KEY]['section']['order']);
    }

    public function testCommandListsItsOwnVarsInTheOwnSection(): void
    {
        $this->createFixtures();

        $rows = $this->loadRows(self::COMMAND, [], IcingaCommand::class);

        $this->assertSame(ObjectCustomPropertySources::OWN, $rows[self::WRTA_KEY]['section']['kind']);
        $this->assertTrue($rows[self::WRTA_KEY]['allow_removal']);
        $this->assertTrue($rows[self::CPL_KEY]['required']);
    }

    public function testHostGetsNoOwnSection(): void
    {
        $this->createFixtures();

        $rows = $this->loadRows(self::WEB_HOST);

        $this->assertSame(ObjectCustomPropertySources::TEMPLATE, $rows[self::OS_KEY]['section']['kind']);
        $this->assertSame(self::LINUX_TEMPLATE, $rows[self::OS_KEY]['section']['name']);
    }

    public function testAddedVarThatIsOnlyInheritedCountsAsNew(): void
    {
        $this->createFixtures();

        $rows = $this->loadRows(self::WEB_TEMPLATE, [$this->propertyUuid(self::OS_KEY)]);

        $this->assertTrue($rows[self::OS_KEY]['new']);
        $this->assertTrue($rows[self::OS_KEY]['allow_removal']);
        $this->assertSame(ObjectCustomPropertySources::OWN, $rows[self::OS_KEY]['section']['kind']);
    }

    public function testAddedVarKeepsThePositionOfTheInheritedRow(): void
    {
        $this->createFixtures();

        $before = array_keys($this->loadRows(self::WEB_TEMPLATE));
        $after = array_keys($this->loadRows(self::WEB_TEMPLATE, [$this->propertyUuid(self::OS_KEY)]));

        $this->assertSame($before, $after);
    }

    public function testAddedVarThatNothingAttachesIsNew(): void
    {
        $this->createFixtures();

        $this->createProperty(self::LOCATION_KEY);
        $rows = $this->loadRows(self::WEB_TEMPLATE, [$this->propertyUuid(self::LOCATION_KEY)]);

        $this->assertTrue($rows[self::LOCATION_KEY]['new']);
    }

    public function testVarAttachedByTheObjectIsNotNewWhenAddedAgain(): void
    {
        $this->createFixtures();

        $this->attach('host', self::WEB_TEMPLATE, self::OS_KEY);
        $rows = $this->loadRows(self::WEB_TEMPLATE, [$this->propertyUuid(self::OS_KEY)]);

        $this->assertArrayNotHasKey('new', $rows[self::OS_KEY]);
    }

    public function testAddingAnInheritedVariableReusesItsRowNumber(): void
    {
        $this->createFixtures();

        $rows = $this->loadRows(self::WEB_TEMPLATE, [$this->propertyUuid(self::OS_KEY)]);
        $index = $this->swappedRowIndex($rows, self::OS_KEY, count($rows));

        $this->assertSame(array_search(self::OS_KEY, array_keys($rows), true), $index);
    }

    public function testAddingABrandNewVariableStillTakesTheNextSlot(): void
    {
        $this->createFixtures();

        $this->createProperty(self::LOCATION_KEY);
        $rows = $this->loadRows(self::WEB_TEMPLATE, [$this->propertyUuid(self::LOCATION_KEY)]);

        // The new row is last, and on screen there were the rows before it
        $this->assertNull($this->swappedRowIndex($rows, self::LOCATION_KEY, count($rows) - 1));
    }

    public function testSwappingTheLastRowOnScreenIsRecognised(): void
    {
        $this->createFixtures();

        $rows = [self::WRTA_KEY => [], self::OS_KEY => []];

        $this->assertSame(1, $this->swappedRowIndex($rows, self::OS_KEY, 2));
    }

    public function testRemovedRowsMoveTheSwappedRowUp(): void
    {
        $this->createFixtures();

        $rows = [self::WRTA_KEY => [], self::OS_KEY => [], self::CPL_KEY => []];
        $index = $this->swappedRowIndex($rows, self::OS_KEY, 2, [self::WRTA_KEY]);

        $this->assertSame(0, $index);
    }

    /**
     * @param array<string, array> $rows
     * @param string[] $removedKeys
     */
    private function swappedRowIndex(array $rows, string $key, int $nextSlotIndex, array $removedKeys = []): ?int
    {
        $form = new CustomVariablesForm(IcingaHost::load(self::WEB_TEMPLATE, $this->getDb()), $rows);

        return self::callMethod($this->newController(), 'getSwappedRowIndex', [
            $form,
            $key,
            $nextSlotIndex,
            $removedKeys,
        ]);
    }

    private function createFixtures(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->tearDown();
        $db = $this->getDb();

        IcingaCommand::create([
            'object_name' => self::COMMAND,
            'object_type' => 'external_object',
            'command'     => 'ping4',
        ], $db)->store();

        foreach ([self::OS_KEY, self::WRTA_KEY, self::CPL_KEY] as $key) {
            $this->createProperty($key);
        }

        $this->attach('command', self::COMMAND, self::WRTA_KEY);
        $this->attach('command', self::COMMAND, self::CPL_KEY, true);
        $this->attach('command', self::COMMAND, self::OS_KEY);

        $this->createHost(self::GENERIC_TEMPLATE, 'template');
        $this->createHost(self::LINUX_TEMPLATE, 'template');
        $this->createHost(self::WEB_TEMPLATE, 'template', [self::GENERIC_TEMPLATE, self::LINUX_TEMPLATE], [
            'check_command' => self::COMMAND,
        ]);
        $this->createHost(self::WEB_HOST, 'object', [self::WEB_TEMPLATE]);

        $this->attach('host', self::GENERIC_TEMPLATE, self::OS_KEY, true);
        $this->attach('host', self::LINUX_TEMPLATE, self::OS_KEY);
    }

    private function createCommandChainFixtures(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->tearDown();
        $db = $this->getDb();

        IcingaCommand::create([
            'object_name' => self::COMMAND_BASE,
            'object_type' => 'template',
        ], $db)->store();

        $child = IcingaCommand::create([
            'object_name' => self::CHILD_COMMAND,
            'object_type' => 'object',
            'command'     => '/usr/lib/nagios/plugins/check_ping',
        ], $db);
        $child->set('imports', [self::COMMAND_BASE]);
        $child->store();

        $this->createProperty(self::WRTA_KEY);
        $this->createProperty(self::CPL_KEY);
        $this->attach('command', self::CHILD_COMMAND, self::WRTA_KEY);
        $this->attach('command', self::COMMAND_BASE, self::CPL_KEY);

        $this->createHost(self::DATABASE_HOST, 'object', [], ['check_command' => self::CHILD_COMMAND]);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            if (IcingaServiceSet::exists(self::SERVICE_SET, $db)) {
                IcingaServiceSet::load(self::SERVICE_SET, $db)->delete();
            }

            $hosts = [
                self::DATABASE_HOST,
                self::WEB_HOST,
                self::WEB_TEMPLATE,
                self::LINUX_TEMPLATE,
                self::GENERIC_TEMPLATE,
            ];
            foreach ($hosts as $name) {
                if (IcingaHost::exists($name, $db)) {
                    IcingaHost::load($name, $db)->delete();
                }
            }

            // The child imports the base, so it has to go first
            foreach ([self::COMMAND, self::CHILD_COMMAND, self::COMMAND_BASE] as $name) {
                if (IcingaCommand::exists($name, $db)) {
                    IcingaCommand::load($name, $db)->delete();
                }
            }

            $dba = $db->getDbAdapter();
            $dba->delete('director_property', $dba->quoteInto('key_name IN (?)', [
                self::OS_KEY,
                self::WRTA_KEY,
                self::CPL_KEY,
                self::LOCATION_KEY,
            ]));
        }

        // The tree remembers ids from the objects deleted above
        IcingaTemplateRepository::clear();

        parent::tearDown();
    }

    /**
     * Load the rows the custom variables form gets for an object, by key name
     *
     * @param string $name
     * @param string[] $addedUuids Properties added this session
     * @param class-string<IcingaHost|IcingaCommand> $class
     *
     * @return array<string, array>
     */
    private function loadRows(string $name, array $addedUuids = [], string $class = IcingaHost::class): array
    {
        $object = $class::load($name, $this->getDb());

        return self::callMethod(
            $this->controllerWithDb(),
            'getObjectCustomProperties',
            [$object, false, $addedUuids]
        );
    }

    private function controllerWithDb(): HostController
    {
        $controller = $this->newController();
        $dbProperty = new ReflectionProperty(ActionController::class, 'db');
        $dbProperty->setValue($controller, $this->getDb());

        return $controller;
    }

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

    private function createProperty(string $key): void
    {
        DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => $key,
            'value_type' => 'string',
            'label'      => $key,
        ], $this->getDb())->store();
    }

    private function propertyUuid(string $key): string
    {
        return Uuid::fromBytes(DirectorProperty::load($key, $this->getDb())->get('uuid'))->toString();
    }

    /**
     * Attach a property to a host or command
     *
     * @param string $type Either host or command
     * @param string $name
     * @param string $key
     * @param bool $required
     */
    private function attach(string $type, string $name, string $key, bool $required = false): void
    {
        $class = $type === 'host' ? IcingaHost::class : IcingaCommand::class;
        $dba = $this->getDb()->getDbAdapter();
        $dba->insert("icinga_{$type}_property", [
            "{$type}_uuid"  => DbUtil::quoteBinaryCompat($class::load($name, $this->getDb())->get('uuid'), $dba),
            'property_uuid' => DbUtil::quoteBinaryCompat(
                DirectorProperty::load($key, $this->getDb())->get('uuid'),
                $dba
            ),
            'required'      => $required ? 'y' : 'n',
        ]);
    }

    private function newController(): HostController
    {
        return (new ReflectionClass(HostController::class))->newInstanceWithoutConstructor();
    }

    /**
     * @param array<int, array{uuid: string, required: bool}> $attachments
     */
    private function callResolve(HostController $controller, array $attachments, string $objectUuid): array
    {
        return self::callMethod($controller, 'resolveEffectiveAttachment', [
            ['key_name' => 'whatever'],
            $attachments,
            $objectUuid,
        ]);
    }
}
