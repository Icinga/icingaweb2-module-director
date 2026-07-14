<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form\DictionaryElements;

use Icinga\Module\Director\Forms\DictionaryElements\Dictionary;
use Icinga\Module\Director\Forms\DictionaryElements\DictionaryItem;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Form\Element\SensitiveElement;
use Ramsey\Uuid\Uuid;
use Tests\Icinga\Module\Director\Form\Lib\TestableDictionaryItem;

class DictionaryItemTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    /** @var string[] key_names of root properties created in tests (for tearDown) */
    private array $createdKeyNames = [];

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
                $rows = $dba->fetchAll(
                    $dba->select()->from('director_property', ['uuid'])->where('key_name = ?', $keyName)
                );
                $uuidsByDepth = [array_map(fn($row) => $row->uuid, $rows)];

                // Go level by level, since parent_uuid has no cascading delete and some
                // tests build properties more than one level deep.
                while (! empty(end($uuidsByDepth))) {
                    $childRows = $dba->fetchAll(
                        $dba->select()
                            ->from('director_property', ['uuid'])
                            ->where('parent_uuid IN (?)', end($uuidsByDepth))
                    );
                    $uuidsByDepth[] = array_map(fn($row) => $row->uuid, $childRows);
                }

                foreach (array_reverse($uuidsByDepth) as $uuids) {
                    if (! empty($uuids)) {
                        $dba->delete('director_property', $dba->quoteInto('uuid IN (?)', $uuids));
                    }
                }
            }
        }

        parent::tearDown();
    }
}
