<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\DirectorObject\Automation;

use Icinga\Module\Director\DirectorObject\Automation\ImportExport;
use Icinga\Module\Director\Objects\DirectorProperty;
use Icinga\Module\Director\Test\BaseTestCase;
use Ramsey\Uuid\Uuid;

class ImportExportTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';

    private ?string $rootKeyName = null;

    public function testCustomPropertyExportListsEachPropertyOnce(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->rootKeyName = self::PREFIX . 'server_profile';

        $root = DirectorProperty::create([
            'uuid'       => Uuid::uuid4()->getBytes(),
            'key_name'   => $this->rootKeyName,
            'value_type' => 'fixed-dictionary',
            'label'      => 'Server Profile',
        ], $db);
        $root->store();
        $rootUuid = $root->get('uuid');

        $child = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => 'tags',
            'parent_uuid' => $rootUuid,
            'value_type'  => 'dynamic-array',
        ], $db);
        $child->store();

        $grandchild = DirectorProperty::create([
            'uuid'        => Uuid::uuid4()->getBytes(),
            'key_name'    => '0',
            'parent_uuid' => $child->get('uuid'),
            'value_type'  => 'string',
        ], $db);
        $grandchild->store();

        $result = (new ImportExport($db))->serializeAllCustomProperties();

        $matchingRoot = array_values(array_filter(
            $result,
            fn($property) => $property->key_name === $this->rootKeyName
        ));
        $this->assertCount(1, $matchingRoot, 'the root property must appear exactly once');

        $matchingChild = array_filter($result, fn($property) => $property->key_name === 'tags');
        $this->assertCount(0, $matchingChild, 'a child property must not also be exported as its own top-level entry');

        $exportedRoot = $matchingRoot[0];
        $this->assertArrayHasKey('tags', $exportedRoot->items);
        $this->assertArrayHasKey('0', $exportedRoot->items['tags']->items);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb() && $this->rootKeyName !== null) {
            $dba = $this->getDb()->getDbAdapter();
            // ON DELETE CASCADE on director_property_parent takes care of the child and grandchild.
            $dba->delete('director_property', $dba->quoteInto('key_name = ?', $this->rootKeyName));
        }

        parent::tearDown();
    }
}
