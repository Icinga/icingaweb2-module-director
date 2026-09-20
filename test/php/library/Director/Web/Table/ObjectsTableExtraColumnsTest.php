<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Table\ObjectsTable;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

class ObjectsTableExtraColumnsTest extends BaseTestCase
{
    private function mockAuth(): Auth
    {
        $auth = $this->getMockBuilder(Auth::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRestrictions'])
            ->getMock();
        $auth->method('getRestrictions')->willReturn([]);
        return $auth;
    }

    public function testAdditionalColumnsAreExplicitAllowlistedAndDisabledForBranches(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $table = ObjectsTable::create('host', $db, $this->mockAuth());
        $table->setAdditionalColumns('check_command, check_interval,check_command');
        $this->assertSame('Check Command', $table->getColumnsToBeRendered()['check_command']);
        $this->assertSame('Check Interval', $table->getColumnsToBeRendered()['check_interval']);

        foreach (['api_key', 'vars.password', 'not_a_column', 'address);DROP TABLE icinga_host;--'] as $invalid) {
            try {
                $table->setAdditionalColumns($invalid);
                $this->fail('Unsupported column accepted: ' . $invalid);
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($invalid, $e->getMessage());
            }
        }

        $branch = ObjectsTable::create('host', $db, $this->mockAuth())
            ->setBranchUuid(Uuid::uuid4());
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('configuration branch');
        $branch->setAdditionalColumns('check_command');
    }

    public function testSelectedHostAndServiceColumnsContainTheirDirectCoreProperties(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $sql = $db->getDbAdapter();
        $auth = $this->mockAuth();
        $command = IcingaCommand::create([
            'object_name' => '___TEST___3088_list_command',
            'object_type' => 'object',
        ], $db);
        $host = IcingaHost::create([
            'object_name' => '___TEST___3088_list_host',
            'object_type' => 'object',
            'address' => '192.0.2.89',
        ], $db);
        $service = IcingaService::create([
            'object_name' => '___TEST___3088_list_service',
            'object_type' => 'object',
            'max_check_attempts' => 7,
        ], $db);

        $command->store();
        $host->set('check_command_id', $command->get('id'));
        $host->store();
        try {
            $service->set('host_id', $host->get('id'));
            $service->set('check_command_id', $command->get('id'));
            $service->store();

            $hosts = ObjectsTable::create('host', $db, $auth)
                ->setAdditionalColumns('check_command');
            $query = $hosts->getQuery();
            $query->where('o.id = ?', $host->get('id'));
            $hostRow = $sql->fetchRow($query);
            $this->assertSame('___TEST___3088_list_command', $hostRow->check_command);
            $this->assertSame('192.0.2.89', $hostRow->address);

            $services = ObjectsTable::create('service', $db, $auth)
                ->setAdditionalColumns('check_command,max_check_attempts');
            $this->assertSame('Check Command', $services->getColumnsToBeRendered()['check_command']);
            $query = $services->getQuery();
            $query->where('o.id = ?', $service->get('id'));
            $serviceRow = $sql->fetchRow($query);
            $this->assertSame('___TEST___3088_list_command', $serviceRow->check_command);
            $this->assertSame(7, (int) $serviceRow->max_check_attempts);
            $this->assertSame('___TEST___3088_list_host', $serviceRow->host);
        } finally {
            if ($service->get('id')) {
                $service->delete();
            }
            $host->delete();
            $command->delete();
        }
    }
}
