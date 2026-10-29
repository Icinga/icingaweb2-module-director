<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Table\ObjectsTableService;

class ObjectsTableServiceCommandUsageTest extends BaseTestCase
{
    public function testOrdinaryServiceListExcludesServiceSetMembers(): void
    {
        $table = $this->createServiceTable();
        $this->assertStringContainsString(
            'o.service_set_id IS NULL',
            (string) $table->getQuery()
        );
    }

    public function testCommandUsageListIncludesServiceSetMembers(): void
    {
        $table = $this->createServiceTable();
        $table->includeServiceSetMembers();

        $query = $table->getQuery();
        $query->where('o.check_command_id = ?', 42);

        $this->assertStringNotContainsString('o.service_set_id IS NULL', (string) $query);
        $this->assertStringContainsString('o.check_command_id', (string) $query);
    }

    private function createServiceTable(): ObjectsTableService
    {
        if ($this->skipForMissingDb()) {
            $this->markTestSkipped('Director database is required');
        }

        $table = new class ($this->getDb(), $this->createMock(Auth::class)) extends ObjectsTableService {
            protected function getRestrictions()
            {
                return [];
            }
        };
        $table->filterObjectType('object');

        return $table;
    }
}
