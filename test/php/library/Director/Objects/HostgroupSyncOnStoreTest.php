<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaHostGroup;
use Icinga\Module\Director\Test\BaseTestCase;

class HostgroupSyncOnStoreTest extends BaseTestCase
{
    public function testCreatedClonedAndModifiedHostMembershipIsImmediatelyCurrent(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $adapter = $db->getDbAdapter();
        $group = IcingaHostGroup::create([
            'object_name' => '___TEST___3092_group',
            'object_type' => 'object',
            'assign_filter' => 'host.vars.tenant=%22___TEST___3092_tenant%22',
        ], $db);
        $template = IcingaHost::create([
            'object_name' => '___TEST___3092_template',
            'object_type' => 'template',
        ], $db);
        $original = IcingaHost::create([
            'object_name' => '___TEST___3092_original',
            'object_type' => 'object',
            'imports' => '___TEST___3092_template',
            'vars.tenant' => '___TEST___3092_tenant',
        ], $db);
        $clone = IcingaHost::create([
            'object_name' => '___TEST___3092_clone',
            'object_type' => 'object',
            'imports' => '___TEST___3092_template',
            'vars.tenant' => '___TEST___3092_tenant',
        ], $db);

        $isMember = static function ($host) use ($adapter, $group): bool {
            return (bool) $adapter->fetchOne(
                $adapter->select()->from('icinga_hostgroup_host_resolved', ['host_id'])
                    ->where('hostgroup_id = ?', $group->get('id'))
                    ->where('host_id = ?', $host->get('id'))
            );
        };

        $group->store();
        $template->store();

        try {
            $original->store();
            $this->assertTrue($isMember($original), 'Creating a matching host must resolve its groups immediately');

            $clone->store();
            $this->assertTrue($isMember($clone), 'Creating a clone must resolve its groups immediately');

            $clone->set('vars.tenant', '___TEST___3092_other');
            $clone->store();
            $this->assertFalse($isMember($clone), 'Changing the variable must immediately revoke group membership');

            $clone->set('vars.tenant', '___TEST___3092_tenant');
            $clone->store();
            $this->assertTrue($isMember($clone), 'Restoring the variable must immediately restore group membership');
        } finally {
            $clone->delete();
            $original->delete();
            $template->delete();
            $group->delete();
        }
    }

    public function testInheritedStaticGroupIsResolvedOnHostCreation(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $adapter = $db->getDbAdapter();
        $group = IcingaHostGroup::create([
            'object_name' => '___TEST___3092_static_group',
            'object_type' => 'object',
        ], $db);
        $template = IcingaHost::create([
            'object_name' => '___TEST___3092_static_template',
            'object_type' => 'template',
        ], $db);
        $host = IcingaHost::create([
            'object_name' => '___TEST___3092_static_host',
            'object_type' => 'object',
            'imports' => '___TEST___3092_static_template',
        ], $db);

        $group->store();
        $template->setGroups('___TEST___3092_static_group');
        $template->store();

        try {
            $host->store();
            $this->assertSame(
                (string) $group->get('id'),
                (string) $adapter->fetchOne(
                    $adapter->select()
                        ->from('icinga_hostgroup_host_resolved', ['hostgroup_id'])
                        ->where('host_id = ?', $host->get('id'))
                ),
                'A host that inherits a static group must be visible immediately after creation'
            );
        } finally {
            $host->delete();
            $template->delete();
            $group->delete();
        }
    }
}
