<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Forms\HostServiceBlacklistForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;
use Throwable;

class HostServiceBlacklistFormTest extends BaseTestCase
{
    private const PREFIX = '___TEST___';
    private const HOST_NAME = self::PREFIX . 'blacklist-host';
    private const SERVICE_NAME = self::PREFIX . 'blacklist-service';

    public function testFailedInsertRollsBackTheOverrideRemoval(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();

        if (IcingaHost::exists(self::HOST_NAME, $db)) {
            IcingaHost::load(self::HOST_NAME, $db)->delete();
        }

        $host = IcingaHost::create([
            'object_name' => self::HOST_NAME,
            'object_type' => 'object',
            'address'     => '127.0.0.1',
        ]);
        $host->store($db);

        $service = IcingaService::create([
            'object_name' => self::SERVICE_NAME,
            'object_type' => 'object',
            'host_id'     => $host->get('id'),
        ]);
        $service->store($db);

        // a host specific tweak for this one service, this is exactly what a
        // failed deactivation must not silently throw away
        $host->overrideServiceVars(self::SERVICE_NAME, (object) ['warn_threshold' => 'critical'])->store();

        // act like someone else already deactivated it a moment ago, the form's
        // own insert is about to collide with this
        $dba->insert('icinga_host_service_blacklist', [
            'host_id'    => $host->get('id'),
            'service_id' => $service->get('id'),
        ]);

        $form = new HostServiceBlacklistForm($db, $host, $service);

        $thrown = false;
        try {
            self::callMethod($form, 'blacklist', []);
        } catch (Throwable $e) {
            $thrown = true;
        }
        $this->assertTrue(
            $thrown,
            'inserting a second blacklist row for the same host and service must raise an exception'
        );

        $reloaded = IcingaHost::load(self::HOST_NAME, $db);
        $overrides = $reloaded->getOverriddenServiceVars(self::SERVICE_NAME);
        $this->assertSame(
            'critical',
            $overrides->warn_threshold,
            'the override must survive a blacklist insert that failed'
        );

        $blacklistRows = (int) $dba->fetchOne(
            $dba->select()->from('icinga_host_service_blacklist', 'COUNT(*)')
                ->where('host_id = ?', $host->get('id'))
                ->where('service_id = ?', $service->get('id'))
        );
        $this->assertSame(1, $blacklistRows, 'the pre-existing blacklist row must be the only one, not duplicated');

        // the failed insert must not leave the transaction open, or the very next
        // query on this connection would fail instead of running normally
        $hostCount = (int) $dba->fetchOne(
            $dba->select()->from('icinga_host', 'COUNT(*)')->where('id = ?', $host->get('id'))
        );
        $this->assertSame(1, $hostCount);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            if (IcingaHost::exists(self::HOST_NAME, $db)) {
                IcingaHost::load(self::HOST_NAME, $db)->delete();
            }
        }

        parent::tearDown();
    }
}
