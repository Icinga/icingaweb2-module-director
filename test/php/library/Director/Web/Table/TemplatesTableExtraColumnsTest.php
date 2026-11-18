<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Table\TemplatesTable;
use InvalidArgumentException;

class TemplatesTableExtraColumnsTest extends BaseTestCase
{
    public function testSelectionIsRestrictedToSupportedCoreProperties(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $table = TemplatesTable::create('host', $this->getDb());
        $this->assertSame(['Template Name'], $table->getColumnsToBeRendered());
        $table->setAdditionalColumns('check_command, address, check_interval,check_command');
        $this->assertSame(
            ['Template Name', 'Check Command', 'Address', 'Check Interval'],
            $table->getColumnsToBeRendered()
        );

        foreach (['api_key', 'object_name', 'cluster', 'address);DROP TABLE icinga_host;--'] as $invalid) {
            try {
                $table->setAdditionalColumns($invalid);
                $this->fail('Unsafe or unsupported column accepted: ' . $invalid);
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString($invalid, $e->getMessage());
            }
        }
    }

    public function testHostTemplateListReadsDirectCoreValuesAndCommandNames(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $command = IcingaCommand::create([
            'object_name' => '___TEST___3088_host_check',
            'object_type' => 'object',
        ], $db);
        $template = IcingaHost::create([
            'object_name' => '___TEST___3088_host_template',
            'object_type' => 'template',
            'address' => '192.0.2.88',
            'check_interval' => '10m',
        ], $db);
        $command->store();
        try {
            $template->set('check_command_id', $command->get('id'));
            $template->store();
            $table = TemplatesTable::create('host', $db)
                ->setAdditionalColumns('check_command,address,check_interval');
            $query = $table->getQuery();
            $query->where('o.object_name = ?', $template->getObjectName());
            $row = $db->getDbAdapter()->fetchRow($query);
            $this->assertSame('___TEST___3088_host_check', $row->check_command);
            $this->assertSame('192.0.2.88', $row->address);
            $this->assertSame('600', $row->check_interval);
            $this->assertStringContainsString('___TEST___3088_host_check', (string) $table->renderRow($row));
        } finally {
            if ($template->get('id')) {
                $template->delete();
            }
            $command->delete();
        }
    }

    public function testServiceTemplateListSupportsCommandsWithoutHostJoins(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $command = IcingaCommand::create([
            'object_name' => '___TEST___3088_service_check',
            'object_type' => 'object',
        ], $db);
        $template = IcingaService::create([
            'object_name' => '___TEST___3088_service_template',
            'object_type' => 'template',
            'max_check_attempts' => 3,
        ], $db);
        $command->store();
        try {
            $template->set('check_command_id', $command->get('id'));
            $template->store();
            $table = TemplatesTable::create('service', $db)
                ->setAdditionalColumns('check_command,max_check_attempts');
            $query = $table->getQuery();
            $query->where('o.object_name = ?', $template->getObjectName());
            $row = $db->getDbAdapter()->fetchRow($query);
            $this->assertSame('___TEST___3088_service_check', $row->check_command);
            $this->assertSame(3, (int) $row->max_check_attempts);
            $this->assertStringContainsString('___TEST___3088_service_check', (string) $table->renderRow($row));
        } finally {
            if ($template->get('id')) {
                $template->delete();
            }
            $command->delete();
        }
    }
}
