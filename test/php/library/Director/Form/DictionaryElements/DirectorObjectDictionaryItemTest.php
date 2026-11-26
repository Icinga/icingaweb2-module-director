<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\DictionaryElements;

use Icinga\Application\Config;
use Icinga\Module\Director\CustomVariable\DirectorObjectReference;
use Icinga\Module\Director\Forms\DictionaryElements\DictionaryItem;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Objects\IcingaUser;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Form\Element\ArrayElement;
use ipl\Html\FormElement\SelectElement;
use Ramsey\Uuid\Uuid;

/**
 * The value input of a director-object custom variable on an object form
 */
class DirectorObjectDictionaryItemTest extends BaseTestCase
{
    private const PREFIX = '___TEST___objitem_';

    public function setUp(): void
    {
        parent::setUp();

        // Same as DictionaryItemTest: DictionaryItem opens its own connection
        if ($this->hasDb()) {
            Config::module('director')->setSection('db', ['resource' => static::getDbResourceName()]);
        }
    }

    public function testSingleObjectIsPickedFromASelectOfExistingObjects(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createUsers();
        $property = $this->createObjectProperty('single', 'string');
        $item = $this->buildItem($property, self::PREFIX . 'alice');

        $select = $item->getElement('var');
        $this->assertInstanceOf(SelectElement::class, $select);
        $this->assertSame('Alice Example', (string) $select->getOption(self::PREFIX . 'alice')->getLabel());
        $this->assertNotNull($select->getOption(self::PREFIX . 'bob'));
        $this->assertNull($select->getOption(self::PREFIX . 'user_template'), 'templates must not be offered');
        $this->assertSame(self::PREFIX . 'alice', $item->getItem()['value']);
    }

    public function testSingleObjectRejectsANameThatIsNoObject(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createUsers();
        $property = $this->createObjectProperty('single_invalid', 'string');
        $item = $this->buildItem($property, null);

        $select = $item->getElement('var');
        $select->setValue(self::PREFIX . 'user_template');
        $this->assertFalse($select->validate()->isValid());
    }

    public function testListOfObjectsUsesAnArrayInput(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createUsers();
        $property = $this->createObjectProperty('list', 'dynamic-array');
        $item = $this->buildItem($property, [self::PREFIX . 'alice', self::PREFIX . 'bob']);

        $this->assertInstanceOf(ArrayElement::class, $item->getElement('var'));
        $this->assertStringContainsString('Director Object Array', (string) $item);
        $this->assertSame([self::PREFIX . 'alice', self::PREFIX . 'bob'], $item->getItem()['value']);
    }

    public function testInheritedListShowsDisplayNamesWithoutCreatingAnOverride(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->createUsers();
        $property = $this->createObjectProperty('inherited_list', 'dynamic-array');
        $data = [
            'uuid'           => $property->get('uuid'),
            'key_name'       => $property->get('key_name'),
            'value_type'     => DirectorObjectReference::VALUE_TYPE,
            'inherited'      => [self::PREFIX . 'alice', self::PREFIX . 'bob'],
            'inherited_from' => 'base-template',
        ];
        $item = new DictionaryItem('0', $data);
        $item->populate(DictionaryItem::prepare($data));
        $item->ensureAssembled();

        $this->assertStringContainsString(
            'Alice Example, ' . self::PREFIX . 'bob (Inherited from base-template)',
            (string) $item
        );
        $this->assertSame([], $item->getItem()['value']);
    }

    /**
     * @param mixed $value
     */
    private function buildItem(DirectorProperty $property, $value): DictionaryItem
    {
        $data = [
            'uuid'       => $property->get('uuid'),
            'key_name'   => $property->get('key_name'),
            'value_type' => DirectorObjectReference::VALUE_TYPE,
            'label'      => 'Notify',
            'value'      => $value,
        ];
        $item = new DictionaryItem('0', $data);
        $item->populate(DictionaryItem::prepare($data));
        $item->ensureAssembled();

        return $item;
    }

    private function createUsers(): void
    {
        $db = $this->getDb();
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

    private function createObjectProperty(string $name, string $itemType): DirectorProperty
    {
        $db = $this->getDb();
        $property = DirectorProperty::import((object) [
            'uuid'        => Uuid::uuid4()->toString(),
            'key_name'    => self::PREFIX . $name,
            'value_type'  => DirectorObjectReference::VALUE_TYPE,
            'label'       => null,
            'parent_uuid' => null,
            'category'    => null,
            'description' => null,
            'object_type' => 'user',
            'items'       => [],
        ], $db);
        $property->store();

        DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $property->get('uuid'),
            'value_type'  => $itemType,
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
