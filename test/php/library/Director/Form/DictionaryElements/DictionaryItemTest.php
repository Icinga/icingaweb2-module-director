<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\DictionaryElements;

use Icinga\Application\Config;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;
use Icinga\Module\Director\Forms\DictionaryElements\DictionaryItem;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Form\Element\SensitiveElement;
use Ramsey\Uuid\Uuid;
use Tests\Icinga\Module\Director\Form\Lib\TestableDictionaryItem;

/**
 * Currently only sensitive types are tested, the tests need to be extended
 * to cover all types.
 */
class DictionaryItemTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    /** @var string[] key_names of root properties created in tests (for tearDown) */
    private array $createdKeyNames = [];

    public function setUp(): void
    {
        parent::setUp();

        // DictionaryItem resolves its own db connection via
        // Config::module('director')->get('db', 'resource'), independently of
        // BaseTestCase's own db handling. Point it at the very same resources.ini
        // entry BaseTestCase uses, so both sides talk to the same test database.
        if ($this->hasDb()) {
            Config::module('director')->setSection('db', ['resource' => static::getDbResourceName()]);
        }
    }

    public function testPrepareScrubsInheritedSecretButKeepsItsPresenceForSensitiveType(): void
    {
        $property = [
            'uuid' => '',
            'key_name' => 'api_token',
            'label' => 'API Token',
            'value_type' => 'sensitive',
            'value' => 's3cr3t-current-value',
            'inherited' => 's3cr3t-inherited-value',
            'inherited_from' => 'webserver-template',
        ];

        $result = DictionaryItem::prepare($property);

        $this->assertStringNotContainsString('s3cr3t-inherited-value', $result['inherited']);
        $this->assertNotEmpty($result['inherited'], 'presence of an inherited value must still be signaled');
    }

    public function testPrepareLeavesInheritedEmptyWhenNothingIsInheritedForSensitiveType(): void
    {
        $property = [
            'uuid' => '',
            'key_name' => 'api_token',
            'label' => 'API Token',
            'value_type' => 'sensitive',
            'value' => 's3cr3t-current-value',
        ];

        $result = DictionaryItem::prepare($property);

        $this->assertSame('', $result['inherited']);
    }

    public function testPrepareMasksAStoredSensitiveValueRatherThanExposingItInTheForm(): void
    {
        // The field can't tell a stored secret apart from a value the user just typed.
        // So prepare() must never send the real secret at all, or it would end up in
        // the page source on the very first load.
        $property = [
            'uuid' => '',
            'key_name' => 'api_token',
            'label' => 'API Token',
            'value_type' => 'sensitive',
            'value' => 's3cr3t-current-value',
        ];

        $result = DictionaryItem::prepare($property);

        $this->assertSame(SensitiveElement::DUMMYPASSWORD, $result['var']);
    }

    public function testPrepareLeavesVarEmptyWhenThereIsNoStoredSensitiveValue(): void
    {
        $property = [
            'uuid' => '',
            'key_name' => 'api_token',
            'label' => 'API Token',
            'value_type' => 'sensitive',
        ];

        $result = DictionaryItem::prepare($property);

        $this->assertSame('', $result['var']);
    }

    public function testGetItemDefaultsSensitiveValueToEmptyStringInFixedArray(): void
    {
        $item = new TestableDictionaryItem('0', []);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-array',
        ]);
        $item->ensureAssembled();

        $this->assertSame('', $item->getItem()['value']);
    }

    public function testGetItemPreservesEnteredSensitiveValue(): void
    {
        $item = new TestableDictionaryItem('api_token', []);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-dictionary',
            'var' => 's3cr3t-value',
        ]);
        $item->ensureAssembled();

        $this->assertSame('s3cr3t-value', $item->getItem()['value']);
    }

    public function testGetItemDefaultsSensitiveValueToEmptyStringWhenInheritedAndLeftBlank(): void
    {
        // The parent template already has a value here (e.g. an SNMP community string
        // like "public"), so 'inherited' is set. The user leaves the field blank, so we
        // just keep the inherited value. This slot must store '' here, not null.
        $item = new TestableDictionaryItem('3', []);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-array',
            'inherited' => '1',
        ]);
        $item->ensureAssembled();

        $this->assertSame('', $item->getItem()['value']);
    }

    public function testGetItemUsesNumberDefaultForFixedArrayItemWithInheritedValueLeftBlank(): void
    {
        // Fixed arrays are all-or-nothing: touching one item un-inherits the whole
        // array, so an untouched sibling must fall back to its type's own default
        // (0 for 'number'), not stay null or show the stale inherited value.
        $item = new TestableDictionaryItem('1', []);
        $item->setTestConfig([
            'type' => 'number',
            'parent_type' => 'fixed-array',
            'inherited' => '1',
        ]);
        $item->ensureAssembled();

        $this->assertSame(0, $item->getItem()['value']);
    }

    public function testGetItemPreservesExistingSensitiveValueWhenLeftUnchanged(): void
    {
        // Left untouched means the browser sends back the DUMMYPASSWORD placeholder,
        // not an empty string. Only that should keep the old secret, otherwise there
        // would be no way to ever clear it.
        $item = new TestableDictionaryItem('api_token', ['value' => 's3cr3t-existing-value']);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-dictionary',
            'var' => SensitiveElement::DUMMYPASSWORD,
        ]);
        $item->ensureAssembled();

        $this->assertSame('s3cr3t-existing-value', $item->getItem()['value']);
    }

    public function testGetItemClearsExistingSensitiveValueWhenExplicitlyEmptied(): void
    {
        // An empty submission means the user cleared the field, so it must clear the
        // stored secret.
        $item = new TestableDictionaryItem('api_token', ['value' => 's3cr3t-existing-value']);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-dictionary',
            'var' => '',
        ]);
        $item->ensureAssembled();

        $this->assertSame('', $item->getItem()['value']);
    }

    public function testGetItemPreservesExistingSensitiveValueWhenInheritedAndLeftUnchanged(): void
    {
        // 'inherited' is set whenever any parent also defines this property, even if
        // this object has its own value too. So getItem()'s "inherited" branch needs
        // the same unchanged-vs-cleared check as above.
        $item = new TestableDictionaryItem('api_token', ['value' => 's3cr3t-existing-value']);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-dictionary',
            'inherited' => '1',
            'var' => SensitiveElement::DUMMYPASSWORD,
        ]);
        $item->ensureAssembled();

        $this->assertSame('s3cr3t-existing-value', $item->getItem()['value']);
    }

    public function testGetItemClearsExistingSensitiveValueWhenInheritedAndExplicitlyEmptied(): void
    {
        $item = new TestableDictionaryItem('api_token', ['value' => 's3cr3t-existing-value']);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-dictionary',
            'inherited' => '1',
            'var' => '',
        ]);
        $item->ensureAssembled();

        $this->assertSame('', $item->getItem()['value']);
    }

    public function testGetItemPreservesExistingSensitiveValueOfZeroWhenLeftUnchanged(): void
    {
        // PHP's empty() treats the string "0" as empty, but a sensitive value of "0"
        // (e.g. a numeric PIN or token) is still a real, previously-set secret that an
        // unchanged submission must not silently erase.
        $item = new TestableDictionaryItem('api_token', ['value' => '0']);
        $item->setTestConfig([
            'type' => 'sensitive',
            'parent_type' => 'fixed-dictionary',
            'var' => SensitiveElement::DUMMYPASSWORD,
        ]);
        $item->ensureAssembled();

        $this->assertSame('0', $item->getItem()['value']);
    }

    public function testFullyInheritedFixedArrayStaysInheritedWhenNothingIsTouched(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // Inherited from a base template and never touched, must not turn into a
        // locally defaulted copy on save.
        $dictionaryItem = $this->buildDiskMirrorsDictionaryItem();

        $result = $dictionaryItem->getItem();

        $this->assertArrayNotHasKey('value', $result);
    }

    public function testFixedArrayMaterializesAsLocalValueWhenOneChildIsChanged(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $dictionaryItem = $this->buildDiskMirrorsDictionaryItem();
        $this->findNestedItem($dictionaryItem, '1')->getElement('var')->setValue('mirror2-standby.example.com');

        $result = $dictionaryItem->getItem();

        $this->assertSame(['', 'mirror2-standby.example.com', ''], $result['value']);
    }

    public function testFixedArrayWithExistingLocalValueStaysUntouchedWhenNothingIsEdited(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // Already has its own local override, nothing here got edited either.
        $dictionaryItem = $this->buildDiskMirrorsDictionaryItem([
            'mirror1.example.com',
            'mirror2.example.com',
            'mirror3.example.com',
        ]);

        $result = $dictionaryItem->getItem();

        $this->assertArrayNotHasKey('value', $result);
    }

    public function testFixedArrayWithExistingLocalValueMaterializesWhenOneChildIsChanged(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $dictionaryItem = $this->buildDiskMirrorsDictionaryItem([
            'mirror1.example.com',
            'mirror2.example.com',
            'mirror3.example.com',
        ]);
        $this->findNestedItem($dictionaryItem, '1')->getElement('var')->setValue('mirror2-standby.example.com');

        $result = $dictionaryItem->getItem();

        $this->assertSame(
            ['mirror1.example.com', 'mirror2-standby.example.com', 'mirror3.example.com'],
            $result['value']
        );
    }

    public function testFixedArrayWithOneStoredSlotStaysUntouchedWhenNothingIsEdited(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // Only one slot was ever filled in, the rest must stay empty rather than
        // getting a type default just because a sibling holds a real value.
        $dictionaryItem = $this->buildDiskMirrorsDictionaryItem([1 => 'mirror2.example.com']);

        $result = $dictionaryItem->getItem();

        $this->assertArrayNotHasKey('value', $result);
    }

    public function testClearingAPreviouslyStoredNumberDoesNotBounceBackToItsTypeDefault(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // Clearing the primary host and the retry count must give back null for the
        // count, not bounce back to 0 just because it is blank now too.
        $db = $this->getDb();
        $parentUuidBytes = Uuid::uuid4()->getBytes();
        $keyName = self::PREFIX . 'backup_targets';
        $this->createdKeyNames[] = $keyName;

        DirectorProperty::create([
            'uuid' => $parentUuidBytes,
            'key_name' => $keyName,
            'value_type' => 'fixed-array',
            'label' => 'Backup Targets',
        ], $db)->store();

        foreach (['0' => 'string', '1' => 'string', '2' => 'number', '3' => 'string'] as $position => $valueType) {
            DirectorProperty::create([
                'uuid' => Uuid::uuid4()->getBytes(),
                'key_name' => $position,
                'parent_uuid' => $parentUuidBytes,
                'value_type' => $valueType,
            ], $db)->store();
        }

        $property = [
            'uuid' => $parentUuidBytes,
            'key_name' => $keyName,
            'value_type' => 'fixed-array',
            'label' => 'Backup Targets',
            'value' => ['backup1.example.com', '', 3, ''],
        ];

        $dictionaryItem = new DictionaryItem('0', $property);
        $dictionaryItem->populate(DictionaryItem::prepare($property));
        $dictionaryItem->ensureAssembled();

        $this->findNestedItem($dictionaryItem, '0')->getElement('var')->setValue('');
        $this->findNestedItem($dictionaryItem, '2')->getElement('var')->setValue('');

        $result = $dictionaryItem->getItem();

        $this->assertSame([null, '', null, ''], $result['value']);
    }

    public function testNestedFixedArrayInsideFixedArrayKeepsItsOwnValueWhenASiblingChanges(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // A fixed array can hold another fixed array as one of its slots. Changing an
        // unrelated sibling still saves the whole outer array, so the untouched nested
        // array must contribute its own value instead of vanishing from it.
        $db = $this->getDb();
        $outerUuidBytes = Uuid::uuid4()->getBytes();
        $keyName = self::PREFIX . 'monitoring_targets';
        $this->createdKeyNames[] = $keyName;

        DirectorProperty::create([
            'uuid' => $outerUuidBytes,
            'key_name' => $keyName,
            'value_type' => 'fixed-array',
            'label' => 'Monitoring Targets',
        ], $db)->store();

        DirectorProperty::create([
            'uuid' => Uuid::uuid4()->getBytes(),
            'key_name' => '0',
            'parent_uuid' => $outerUuidBytes,
            'value_type' => 'string',
        ], $db)->store();

        $nestedUuidBytes = Uuid::uuid4()->getBytes();
        DirectorProperty::create([
            'uuid' => $nestedUuidBytes,
            'key_name' => '1',
            'parent_uuid' => $outerUuidBytes,
            'value_type' => 'fixed-array',
        ], $db)->store();

        foreach (['0', '1'] as $nestedPosition) {
            DirectorProperty::create([
                'uuid' => Uuid::uuid4()->getBytes(),
                'key_name' => $nestedPosition,
                'parent_uuid' => $nestedUuidBytes,
                'value_type' => 'string',
            ], $db)->store();
        }

        $property = [
            'uuid' => $outerUuidBytes,
            'key_name' => $keyName,
            'value_type' => 'fixed-array',
            'label' => 'Monitoring Targets',
        ];

        $dictionaryItem = new DictionaryItem('0', $property);
        $dictionaryItem->populate(DictionaryItem::prepare($property));
        $dictionaryItem->ensureAssembled();

        $this->findNestedItem($dictionaryItem, '0')->getElement('var')->setValue('primary.example.com');

        $result = $dictionaryItem->getItem();

        $this->assertSame(['primary.example.com', ['', '']], $result['value']);
    }

    public function testMergeChildValuesAttachesMatchingValueByKeyName(): void
    {
        $propertyItems = [
            ['key_name' => 'threshold', 'value_type' => 'number'],
        ];
        $values = ['value' => ['threshold' => 5]];

        $result = DictionaryItem::mergeChildValues($propertyItems, 'fixed-dictionary', $values);

        $this->assertSame(5, $result['threshold']['value']);
    }

    public function testMergeChildValuesAttachesMatchingInheritedValueAndItsSource(): void
    {
        $propertyItems = [
            ['key_name' => 'community', 'value_type' => 'sensitive'],
        ];
        $values = [
            'inherited' => ['community' => 'public'],
            'inherited_from' => 'snmp-template',
        ];

        $result = DictionaryItem::mergeChildValues($propertyItems, 'fixed-dictionary', $values);

        $this->assertSame('public', $result['community']['inherited']);
        $this->assertSame('snmp-template', $result['community']['inherited_from']);
    }

    public function testMergeChildValuesLeavesChildrenWithoutAMatchUntouched(): void
    {
        $propertyItems = [
            ['key_name' => 'team', 'value_type' => 'string'],
        ];
        $values = ['value' => ['some_other_key' => 'ops']];

        $result = DictionaryItem::mergeChildValues($propertyItems, 'fixed-dictionary', $values);

        $this->assertArrayNotHasKey('value', $result['team']);
    }

    public function testMergeChildValuesStampsTheParentTypeOntoEveryChild(): void
    {
        $propertyItems = [
            ['key_name' => 'threshold', 'value_type' => 'number'],
            ['key_name' => 'token', 'value_type' => 'sensitive'],
        ];

        $result = DictionaryItem::mergeChildValues($propertyItems, 'dynamic-dictionary', []);

        $this->assertSame('dynamic-dictionary', $result['threshold']['parent_type']);
        $this->assertSame('dynamic-dictionary', $result['token']['parent_type']);
    }

    public function testMergeChildValuesKeysTheResultByKeyNameRatherThanPosition(): void
    {
        $propertyItems = [
            ['key_name' => 'second', 'value_type' => 'string'],
            ['key_name' => 'first', 'value_type' => 'string'],
        ];

        $result = DictionaryItem::mergeChildValues($propertyItems, 'fixed-dictionary', []);

        $this->assertSame(['second', 'first'], array_keys($result));
    }

    public function testNestedSensitiveFieldPreservesExistingValueWhenLeftUnchanged(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // assemble() must pass the parent's own value down to fetchChildrenItems(), so
        // each child gets its own current value too. Without that, a nested sensitive
        // field has nothing to fall back on when it comes back as the DUMMYPASSWORD
        // placeholder.
        $dictionaryItem = $this->buildSnmpV3DictionaryItem();

        $authPassword = $this->findNestedItem($dictionaryItem, 'auth_password');
        $authPassword->getElement('var')->setValue(SensitiveElement::DUMMYPASSWORD);

        $result = $dictionaryItem->getItem();

        $this->assertSame('s3cr3t-auth-pass', $result['value']['auth_password']);
    }

    public function testNestedSensitiveFieldClearsExistingValueWhenExplicitlyEmptied(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $dictionaryItem = $this->buildSnmpV3DictionaryItem();

        $authPassword = $this->findNestedItem($dictionaryItem, 'auth_password');
        $authPassword->getElement('var')->setValue('');

        $result = $dictionaryItem->getItem();

        $this->assertSame('', $result['value']['auth_password']);
    }

    public function testSensitiveFieldDirectlyInsideDynamicDictionaryEntryPreservesValueWhenLeftUnchanged(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $dictionaryItem = $this->buildDiskThresholdsDictionaryItem();

        $result = $dictionaryItem->getItem();

        $this->assertSame('s3cr3t-disk-token', $result['value']['/var/log']['api_token']);
    }

    public function testSensitiveFieldNestedAsGrandchildOfDynamicDictionaryPreservesValueWhenLeftUnchanged(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $dictionaryItem = $this->buildDiskThresholdsDictionaryItem();

        $result = $dictionaryItem->getItem();

        $this->assertSame('s3cr3t-nested-token', $result['value']['/var/log']['disk_users']['token']);
    }

    public function testSensitiveFieldsInsideDynamicDictionaryEntryAreClearedWhenExplicitlyEmptied(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $dictionaryItem = $this->buildDiskThresholdsDictionaryItem(clearInsteadOfUnchanged: true);

        $result = $dictionaryItem->getItem();

        $this->assertSame('', $result['value']['/var/log']['api_token']);
        $this->assertSame('', $result['value']['/var/log']['disk_users']['token']);
    }

    public function testSensitiveFieldsInsideDynamicDictionaryEntryKeepANewlyTypedValue(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $dictionaryItem = $this->buildDiskThresholdsDictionaryItem(retypedValue: 'freshly-typed-value');

        $result = $dictionaryItem->getItem();

        $this->assertSame('freshly-typed-value', $result['value']['/var/log']['api_token']);
        $this->assertSame('freshly-typed-value', $result['value']['/var/log']['disk_users']['token']);
    }

    /**
     * Build a dynamic-dictionary DictionaryItem ("disk_thresholds") backed by real
     * director_property rows. It has one sensitive field directly on each entry
     * ("api_token") and one nested one level deeper ("disk_users.token"). Loads and
     * populates it the same way the controller does, so the sensitive fields are
     * submitted as either the DUMMYPASSWORD placeholder (untouched), an empty string
     * (cleared), or a new value, never the real secret.
     */
    private function buildDiskThresholdsDictionaryItem(
        bool $clearInsteadOfUnchanged = false,
        ?string $retypedValue = null
    ): DictionaryItem {
        $db = $this->getDb();
        $parentUuidBytes = Uuid::uuid4()->getBytes();
        $keyName = self::PREFIX . 'disk_thresholds';
        $this->createdKeyNames[] = $keyName;

        DirectorProperty::create([
            'uuid' => $parentUuidBytes,
            'key_name' => $keyName,
            'value_type' => 'dynamic-dictionary',
            'label' => 'Disk Thresholds',
        ], $db)->store();

        DirectorProperty::create([
            'uuid' => Uuid::uuid4()->getBytes(),
            'key_name' => 'threshold',
            'parent_uuid' => $parentUuidBytes,
            'value_type' => 'number',
        ], $db)->store();

        DirectorProperty::create([
            'uuid' => Uuid::uuid4()->getBytes(),
            'key_name' => 'api_token',
            'parent_uuid' => $parentUuidBytes,
            'value_type' => 'sensitive',
        ], $db)->store();

        $diskUsersUuidBytes = Uuid::uuid4()->getBytes();
        DirectorProperty::create([
            'uuid' => $diskUsersUuidBytes,
            'key_name' => 'disk_users',
            'parent_uuid' => $parentUuidBytes,
            'value_type' => 'fixed-dictionary',
        ], $db)->store();

        DirectorProperty::create([
            'uuid' => Uuid::uuid4()->getBytes(),
            'key_name' => 'team',
            'parent_uuid' => $diskUsersUuidBytes,
            'value_type' => 'string',
        ], $db)->store();

        DirectorProperty::create([
            'uuid' => Uuid::uuid4()->getBytes(),
            'key_name' => 'token',
            'parent_uuid' => $diskUsersUuidBytes,
            'value_type' => 'sensitive',
        ], $db)->store();

        $property = [
            'uuid' => $parentUuidBytes,
            'key_name' => $keyName,
            'value_type' => 'dynamic-dictionary',
            'label' => 'Disk Thresholds',
            'value' => [
                '/var/log' => [
                    'threshold' => 5,
                    'api_token' => 's3cr3t-disk-token',
                    'disk_users' => [
                        'team' => 'ops',
                        'token' => 's3cr3t-nested-token',
                    ],
                ],
            ],
        ];

        $preparedValues = DictionaryItem::prepare($property);

        $dictionaryItem = new DictionaryItem('0', $property);
        $dictionaryItem->populate($preparedValues);
        $submittedValues = match (true) {
            $retypedValue !== null => $this->retypeSensitiveFields($preparedValues, $retypedValue),
            $clearInsteadOfUnchanged => $this->clearSensitiveFields($preparedValues),
            default => $this->markSensitiveFieldsUnchanged($preparedValues),
        };
        $dictionaryItem->populate($submittedValues);
        $dictionaryItem->ensureAssembled();

        return $dictionaryItem;
    }

    /**
     * Replace every sensitive field's value with the DUMMYPASSWORD placeholder, like a
     * browser resubmitting an untouched field.
     */
    private function markSensitiveFieldsUnchanged(array $node): array
    {
        if (($node['type'] ?? null) === 'sensitive') {
            $node['var'] = SensitiveElement::DUMMYPASSWORD;
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->markSensitiveFieldsUnchanged($value);
            }
        }

        return $node;
    }

    /**
     * Replace every sensitive field's value with an empty string, like a browser
     * submitting a field the user cleared.
     */
    private function clearSensitiveFields(array $node): array
    {
        if (($node['type'] ?? null) === 'sensitive') {
            $node['var'] = '';
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->clearSensitiveFields($value);
            }
        }

        return $node;
    }

    /**
     * Replace every sensitive field's value with a new value, like a browser
     * submitting a field the user just changed.
     */
    private function retypeSensitiveFields(array $node, string $newValue): array
    {
        if (($node['type'] ?? null) === 'sensitive') {
            $node['var'] = $newValue;
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->retypeSensitiveFields($value, $newValue);
            }
        }

        return $node;
    }

    /**
     * Build a fixed-dictionary DictionaryItem ("snmp_v3") with a string child
     * ("username") and a sensitive child ("auth_password"), backed by real
     * director_property rows, mirroring what the controller constructs for an
     * existing object.
     */
    private function buildSnmpV3DictionaryItem(): DictionaryItem
    {
        $db = $this->getDb();
        $parentUuidBytes = Uuid::uuid4()->getBytes();
        $keyName = self::PREFIX . 'snmp_v3';
        $this->createdKeyNames[] = $keyName;

        DirectorProperty::create([
            'uuid' => $parentUuidBytes,
            'key_name' => $keyName,
            'value_type' => 'fixed-dictionary',
            'label' => 'SNMP v3',
        ], $db)->store();

        DirectorProperty::create([
            'uuid' => Uuid::uuid4()->getBytes(),
            'key_name' => 'username',
            'parent_uuid' => $parentUuidBytes,
            'value_type' => 'string',
        ], $db)->store();

        DirectorProperty::create([
            'uuid' => Uuid::uuid4()->getBytes(),
            'key_name' => 'auth_password',
            'parent_uuid' => $parentUuidBytes,
            'value_type' => 'sensitive',
        ], $db)->store();

        $dictionaryItem = new DictionaryItem('0', [
            'uuid' => $parentUuidBytes,
            'key_name' => $keyName,
            'value_type' => 'fixed-dictionary',
            'label' => 'SNMP v3',
            'value' => [
                'username' => 'monitoring',
                'auth_password' => 's3cr3t-auth-pass',
            ],
        ]);
        $dictionaryItem->ensureAssembled();

        return $dictionaryItem;
    }

    /**
     * Build a fixed-array DictionaryItem ("disk_mirrors") with 3 string slots
     *
     * With no $localValue it is purely inherited. With $localValue given it already
     * has its own local override, a sparse array fills only some of its slots.
     */
    private function buildDiskMirrorsDictionaryItem(?array $localValue = null): DictionaryItem
    {
        $db = $this->getDb();
        $parentUuidBytes = Uuid::uuid4()->getBytes();
        $keyName = self::PREFIX . 'disk_mirrors';
        $this->createdKeyNames[] = $keyName;

        DirectorProperty::create([
            'uuid' => $parentUuidBytes,
            'key_name' => $keyName,
            'value_type' => 'fixed-array',
            'label' => 'Disk Mirrors',
        ], $db)->store();

        foreach (['0', '1', '2'] as $position) {
            DirectorProperty::create([
                'uuid' => Uuid::uuid4()->getBytes(),
                'key_name' => $position,
                'parent_uuid' => $parentUuidBytes,
                'value_type' => 'string',
            ], $db)->store();
        }

        $property = [
            'uuid' => $parentUuidBytes,
            'key_name' => $keyName,
            'value_type' => 'fixed-array',
            'label' => 'Disk Mirrors',
        ];

        if ($localValue !== null) {
            $property['value'] = $localValue;
        } else {
            $property['inherited'] = [
                '0' => 'mirror1.example.com',
                '1' => 'mirror2.example.com',
                '2' => 'mirror3.example.com',
            ];
            $property['inherited_from'] = 'storage-template';
        }

        $preparedValues = DictionaryItem::prepare($property);

        $dictionaryItem = new DictionaryItem('0', $property);
        $dictionaryItem->populate($preparedValues);
        $dictionaryItem->ensureAssembled();

        return $dictionaryItem;
    }

    /**
     * Find the nested DictionaryItem for the given key name inside a fixed-dictionary/
     * fixed-array DictionaryItem's 'var' Dictionary.
     */
    private function findNestedItem(DictionaryItem $parent, string $keyName): DictionaryItem
    {
        /** @var Dictionary $dictionary */
        $dictionary = $parent->getElement('var');
        foreach ($dictionary->ensureAssembled()->getElements() as $element) {
            if (
                $element instanceof DictionaryItem
                && $element->ensureAssembled()->getElement('name')->getValue() === $keyName
            ) {
                return $element;
            }
        }

        $this->fail("No nested item named '$keyName' found");
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $dba = $this->getDb()->getDbAdapter();
            foreach ($this->createdKeyNames as $keyName) {
                $rootUuids = $dba->fetchCol(
                    $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', $keyName)
                );
                $uuidsByDepth = [array_map([DbUtil::class, 'binaryResult'], $rootUuids)];

                // Go level by level, since parent_uuid has no cascading delete and some
                // tests build properties more than one level deep.
                while (! empty(end($uuidsByDepth))) {
                    $childUuids = $dba->fetchCol(
                        $dba->select()
                            ->from('director_property', ['uuid'])
                            ->where('parent_uuid IN (?)', DbUtil::quoteBinaryCompat(end($uuidsByDepth), $dba))
                    );
                    $uuidsByDepth[] = array_map([DbUtil::class, 'binaryResult'], $childUuids);
                }

                foreach (array_reverse($uuidsByDepth) as $uuids) {
                    if (! empty($uuids)) {
                        $dba->delete(
                            'director_property',
                            $dba->quoteInto('uuid IN (?)', DbUtil::quoteBinaryCompat($uuids, $dba))
                        );
                    }
                }
            }
        }

        parent::tearDown();
    }
}
