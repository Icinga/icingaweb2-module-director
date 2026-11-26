<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\CustomVariable;

use Icinga\Module\Director\CustomVariable\CustomVariableValueValidator;
use Icinga\Module\Director\CustomVariable\DirectorObjectReference;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\IcingaNotificationForm;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaUser;
use Icinga\Module\Director\Test\BaseTestCase;
use InvalidArgumentException;
use ipl\Html\ValidHtml;
use Ramsey\Uuid\Uuid;
use Tests\Icinga\Module\Director\Lib\ProvidedHook\Icingadb\TestableCustomVarRenderer;

class DirectorObjectReferenceTest extends BaseTestCase
{
    private const PREFIX = '___TEST___objref_';

    public function testEnumObjectsListsObjectsButNoTemplates(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createUsers($db);

        $users = DirectorObjectReference::enumObjects($db->getDbAdapter(), 'user');

        $this->assertArrayHasKey(self::PREFIX . 'alice', $users);
        $this->assertArrayHasKey(self::PREFIX . 'bob', $users);
        $this->assertArrayNotHasKey(self::PREFIX . 'user_template', $users, 'templates must not be offered');
    }

    public function testEnumObjectsUsesDisplayNameWhenSet(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createUsers($db);

        $users = DirectorObjectReference::enumObjects($db->getDbAdapter(), 'user');

        $this->assertSame('Alice Example', $users[self::PREFIX . 'alice']);
        $this->assertSame(self::PREFIX . 'bob', $users[self::PREFIX . 'bob']);
    }

    public function testEnumObjectsRejectsUnknownObjectType(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->expectException(InvalidArgumentException::class);
        DirectorObjectReference::enumObjects($this->getDb()->getDbAdapter(), 'director_property');
    }

    public function testLinkReplacesThePreviousObjectType(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createObjectProperty($db, 'relink', 'user');
        $uuid = $property->get('uuid');
        $dba = $db->getDbAdapter();

        $this->assertSame('user', DirectorObjectReference::fetchObjectType($dba, $uuid));

        DirectorObjectReference::link($dba, $uuid, 'usergroup');

        $this->assertSame('usergroup', DirectorObjectReference::fetchObjectType($dba, $uuid));
        $count = $dba->fetchOne(
            $dba->select()
                ->from(DirectorObjectReference::TABLE, ['cnt' => 'COUNT(*)'])
                ->where('property_uuid = ?', DbUtil::quoteBinaryCompat($uuid, $dba))
        );
        $this->assertEquals(1, (int) $count, 'a property references exactly one object type');
    }

    public function testLinkRejectsUnknownObjectType(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createObjectProperty($db, 'bad_link', 'user');

        $this->expectException(InvalidArgumentException::class);
        DirectorObjectReference::link($db->getDbAdapter(), $property->get('uuid'), 'timeperiod');
    }

    public function testDeletingThePropertyRemovesItsLink(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createObjectProperty($db, 'cascade', 'user');
        $uuid = $property->get('uuid');
        $dba = $db->getDbAdapter();

        $dba->delete('director_property', $dba->quoteInto('uuid = ?', DbUtil::quoteBinaryCompat($uuid, $dba)));

        $this->assertNull(DirectorObjectReference::fetchObjectType($dba, $uuid));
    }

    public function testArrayItemTypeRejectsScalarValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createObjectProperty($db, 'shape_list', 'user', true);

        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertMatchesType(
            'notify',
            self::PREFIX . 'alice',
            DirectorObjectReference::VALUE_TYPE,
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
    }

    public function testStringItemTypeRejectsArrayValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createObjectProperty($db, 'shape_single', 'user');

        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertMatchesType(
            'notify',
            [self::PREFIX . 'alice'],
            DirectorObjectReference::VALUE_TYPE,
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
    }

    public function testValueAllowedAcceptsExistingObjects(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createUsers($db);
        $property = $this->createObjectProperty($db, 'allowed', 'user', true);

        CustomVariableValueValidator::assertValueAllowed(
            'notify',
            [self::PREFIX . 'alice', self::PREFIX . 'bob'],
            DirectorObjectReference::VALUE_TYPE,
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
        $this->addToAssertionCount(1);
    }

    public function testValueAllowedRejectsUnknownObject(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createUsers($db);
        $property = $this->createObjectProperty($db, 'unknown', 'user', true);

        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertValueAllowed(
            'notify',
            [self::PREFIX . 'alice', self::PREFIX . 'nobody'],
            DirectorObjectReference::VALUE_TYPE,
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
    }

    public function testValueAllowedRejectsTemplate(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createUsers($db);
        $property = $this->createObjectProperty($db, 'template', 'user');

        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertValueAllowed(
            'notify',
            self::PREFIX . 'user_template',
            DirectorObjectReference::VALUE_TYPE,
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
    }

    public function testValueAllowedRejectsObjectOfAnotherType(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createUsers($db);
        $property = $this->createObjectProperty($db, 'other_type', 'usergroup');

        $this->expectException(InvalidArgumentException::class);
        CustomVariableValueValidator::assertValueAllowed(
            'notify',
            self::PREFIX . 'alice',
            DirectorObjectReference::VALUE_TYPE,
            Uuid::fromBytes($property->get('uuid')),
            $db
        );
    }

    public function testExportCarriesTheObjectType(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createObjectProperty($db, 'export', 'usergroup', true);

        $exported = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db)->export();

        $this->assertSame('usergroup', $exported->object_type);
        $this->assertCount(1, $exported->items, 'the item type child must be exported too');
    }

    public function testImportOfANewPropertyRestoresTheObjectType(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createObjectProperty($db, 'import_new', 'host');
        $exported = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db)->export();

        // Restore it under a different name and uuid, like on another Director instance
        $exported->uuid = Uuid::uuid4()->toString();
        $exported->key_name = self::PREFIX . 'import_new_copy';
        $exported->items = [];
        $imported = DirectorProperty::import($exported, $db);
        $imported->store();

        $this->assertSame(
            'host',
            DirectorObjectReference::fetchObjectType($db->getDbAdapter(), $imported->get('uuid'))
        );
    }

    public function testImportOfAChangedObjectTypeIsStored(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createObjectProperty($db, 'import_changed', 'user');
        $exported = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db)->export();

        $exported->object_type = 'usergroup';
        $imported = DirectorProperty::import($exported, $db);

        $this->assertTrue($imported->hasBeenModified(), 'only the object type changed, it must still be stored');
        $imported->store();

        $this->assertSame(
            'usergroup',
            DirectorObjectReference::fetchObjectType($db->getDbAdapter(), $property->get('uuid'))
        );
    }

    public function testImportRejectsUnknownObjectType(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $property = $this->createObjectProperty($db, 'import_bad', 'user');
        $exported = DirectorProperty::loadWithUniqueId(Uuid::fromBytes($property->get('uuid')), $db)->export();
        $exported->object_type = 'timeperiod';

        $this->expectException(InvalidArgumentException::class);
        DirectorProperty::import($exported, $db);
    }

    public function testNotificationFormOffersListsOfTheRightObjectType(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createObjectProperty($db, 'notify_users', 'user', true);
        $this->createObjectProperty($db, 'single_user', 'user');
        $this->createObjectProperty($db, 'notify_groups', 'usergroup', true);

        $form = new IcingaNotificationForm();
        $form->setDb($db);

        $userVars = self::callMethod($form, 'enumDirectorObjectFields', ['user']);
        $this->assertArrayHasKey(self::PREFIX . 'notify_users', $userVars);
        $this->assertArrayNotHasKey(
            self::PREFIX . 'single_user',
            $userVars,
            'Icinga needs a list of users there, a single user can not be used'
        );
        $this->assertArrayNotHasKey(self::PREFIX . 'notify_groups', $userVars);

        $groupVars = self::callMethod($form, 'enumDirectorObjectFields', ['usergroup']);
        $this->assertArrayHasKey(self::PREFIX . 'notify_groups', $groupVars);
        $this->assertArrayNotHasKey(self::PREFIX . 'notify_users', $groupVars);
    }

    public function testIcingadbRendererShowsDisplayNamesOfReferencedObjects(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createUsers($db);
        $property = $this->createObjectProperty($db, 'rendered', 'user', true);
        $key = self::PREFIX . 'rendered';

        $renderer = new TestableCustomVarRenderer();
        $renderer->seedPropertyValueType($key, DirectorObjectReference::VALUE_TYPE);
        self::callMethod($renderer, 'loadNestedPropertyConfig', [$db, [[
            'uuid'       => $property->get('uuid'),
            'key_name'   => $key,
            'value_type' => DirectorObjectReference::VALUE_TYPE,
        ]]]);

        $rendered = $renderer->renderCustomVarValue($key, [self::PREFIX . 'alice', self::PREFIX . 'bob']);

        $this->assertInstanceOf(ValidHtml::class, $rendered[0]);
        $this->assertStringContainsString('Alice Example', $rendered[0]->render());
        $this->assertSame(
            self::PREFIX . 'bob',
            $rendered[1],
            'an object without a display name of its own is shown by its name'
        );
    }

    private function createUsers(Db $db): void
    {
        IcingaUser::create([
            'object_name' => self::PREFIX . 'user_template',
            'object_type' => 'template',
        ], $db)->store();

        IcingaUser::create([
            'object_name'  => self::PREFIX . 'alice',
            'object_type'  => 'object',
            'display_name' => 'Alice Example',
        ], $db)->store();

        IcingaUser::create([
            'object_name' => self::PREFIX . 'bob',
            'object_type' => 'object',
        ], $db)->store();
    }

    private function createObjectProperty(
        Db $db,
        string $name,
        string $objectType,
        bool $holdsList = false
    ): DirectorProperty {
        $property = DirectorProperty::import((object) [
            'uuid'        => Uuid::uuid4()->toString(),
            'key_name'    => self::PREFIX . $name,
            'value_type'  => DirectorObjectReference::VALUE_TYPE,
            'label'       => null,
            'parent_uuid' => null,
            'category'    => null,
            'description' => null,
            'object_type' => $objectType,
            'items'       => [],
        ], $db);
        $property->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $property->get('uuid'),
            'value_type'  => $holdsList ? 'dynamic-array' : 'string',
        ], $db)->store();

        return $property;
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $dba = $this->getDb()->getDbAdapter();
            // children and the object type link go along with their property
            $dba->delete('director_property', $dba->quoteInto('key_name LIKE ?', self::PREFIX . '%'));
            $dba->delete('icinga_user', $dba->quoteInto('object_name LIKE ?', self::PREFIX . '%'));
        }

        parent::tearDown();
    }
}
