<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Auth\Restriction;
use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Objects\HostGroupMembershipResolver;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaHostGroup;
use Icinga\Module\Director\Restriction\HostgroupRestriction;
use Icinga\Module\Director\Test\BaseTestCase;

class HostGroupCloneAccessRegressionTest extends BaseTestCase
{
    public function testNonAdminCloneIsVisibleAfterTenantGroupIsAddedAsApplyRule(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $sql = $db->getDbAdapter();
        $tenantName = '___TEST___3092_tenant_group';
        $networkName = '___TEST___3092_network_group';
        $tenant = IcingaHostGroup::create([
            'object_name' => $tenantName,
            'object_type' => 'object',
        ], $db);
        $network = IcingaHostGroup::create([
            'object_name' => $networkName,
            'object_type' => 'object',
        ], $db);
        $tenantTemplate = IcingaHost::create([
            'object_name' => '___TEST___3092_tenant_template',
            'object_type' => 'template',
            'vars.tenant' => '___TEST___3092_customer',
        ], $db);
        $networkTemplate = IcingaHost::create([
            'object_name' => '___TEST___3092_network_template',
            'object_type' => 'template',
        ], $db);
        $host = IcingaHost::create([
            'object_name' => '___TEST___3092_source_host',
            'object_type' => 'object',
            // Network is last and overrides the tenant static group.
            'imports' => [
                '___TEST___3092_tenant_template',
                '___TEST___3092_network_template',
            ],
        ], $db);
        $tenant->store();
        $network->store();
        $tenantTemplate->setGroups($tenantName);
        $tenantTemplate->store();
        $networkTemplate->setGroups($networkName);
        $networkTemplate->store();
        $store = new DbObjectStore($db);
        $cloned = null;

        $auth = $this->getMockBuilder(Auth::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRestrictions'])
            ->getMock();
        $auth->method('getRestrictions')->willReturnCallback(
            static function ($type) use ($tenantName) {
                return $type === Restriction::FILTER_HOSTGROUPS ? [$tenantName] : [];
            }
        );
        $restriction = new HostgroupRestriction($db, $auth);

        try {
            $host->store();
            $this->assertFalse($restriction->allowsHost($host), 'Import order initially denies the tenant host');

            // The newly added tenant apply rule must update already stored hosts.
            $tenant->set('assign_filter', 'host.vars.tenant=%22___TEST___3092_customer%22');
            $tenant->store();
            $this->assertTrue(
                $restriction->allowsHost(IcingaHost::load($host->getObjectName(), $db)),
                'The new apply rule should grant access to the original host'
            );

            // Follow IcingaCloneObjectForm::onSuccess() exactly for a non-admin
            // cloning an ordinary host without services or configuration branches.
            $cloned = $host::fromPlainObject(
                $host->toPlainObject(false),
                $db
            )->set('object_name', '___TEST___3092_new_clone');
            $cloned->set('display_name', null);
            $cloned->set('api_key', null);
            $this->assertTrue($store->store($cloned));

            $rows = $sql->fetchPairs(
                $sql->select()->from('icinga_hostgroup_host_resolved', ['hostgroup_id', 'hostgroup_id'])
                    ->where('host_id = ?', $cloned->get('id'))
            );
            $this->assertArrayHasKey(
                $tenant->get('id'),
                $rows,
                'The clone must have the tenant membership before any maintenance task'
            );
            $this->assertTrue(
                $restriction->allowsHost(IcingaHost::load($cloned->getObjectName(), $db)),
                'The freshly cloned host should be accessible through the tenant hostgroup restriction'
            );
        } finally {
            if ($cloned && $cloned->get('id')) {
                $cloned->delete();
            }
            $host->delete();
            $networkTemplate->delete();
            $tenantTemplate->delete();
            $network->delete();
            $tenant->delete();
        }
    }
    public function testCloneUsesFreshlyResolvedGroupsWhenTenantRuleDependsOnAnotherAppliedGroup(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $sql = $db->getDbAdapter();
        $scopeName = '___TEST___3092_scope';
        $tenantName = '___TEST___3092_tenant_depends_on_scope';
        $scope = IcingaHostGroup::create([
            'object_name' => $scopeName,
            'object_type' => 'object',
            'assign_filter' => 'host.vars.tenant=%22___TEST___3092_customer%22',
        ], $db);
        $tenant = IcingaHostGroup::create([
            'object_name' => $tenantName,
            'object_type' => 'object',
        ], $db);
        $template = IcingaHost::create([
            'object_name' => '___TEST___3092_scope_template',
            'object_type' => 'template',
            'vars.tenant' => '___TEST___3092_customer',
        ], $db);
        $source = IcingaHost::create([
            'object_name' => '___TEST___3092_scope_source',
            'object_type' => 'object',
            'imports' => '___TEST___3092_scope_template',
        ], $db);
        $clone = null;

        $inGroup = static function ($host, $group) use ($sql): bool {
            return (bool) $sql->fetchOne(
                $sql->select()
                    ->from('icinga_hostgroup_host_resolved', ['host_id'])
                    ->where('hostgroup_id = ?', $group->get('id'))
                    ->where('host_id = ?', $host->get('id'))
            );
        };

        $scope->store();
        $tenant->store();
        $template->store();

        try {
            $source->store();
            $this->assertTrue($inGroup($source, $scope));
            $this->assertFalse($inGroup($source, $tenant));

            $tenant->set('assign_filter', 'host.groups=%22___TEST___3092_scope%2A%22');
            $tenant->store();
            $this->assertTrue(
                $inGroup($source, $tenant),
                'Adding an apply rule based on an existing applied group must make the old host visible'
            );

            $clone = $source::fromPlainObject($source->toPlainObject(false), $db)
                ->set('object_name', '___TEST___3092_scope_clone');
            $clone->set('display_name', null);
            $clone->set('api_key', null);
            (new DbObjectStore($db))->store($clone);
            $visibleBeforeRefresh = $inGroup($clone, $tenant);

            // A full maintenance refresh is what the issue author reports
            // restoring access; assert both sides of that transition.
            (new HostGroupMembershipResolver($db))->refreshAllMappings();
            $this->assertTrue($inGroup($clone, $tenant), 'Maintenance must restore tenant membership');
            $this->assertTrue(
                $visibleBeforeRefresh,
                'The clone must be assigned to the tenant group before any maintenance refresh'
            );
        } finally {
            if ($clone && $clone->get('id')) {
                $clone->delete();
            }
            $source->delete();
            $template->delete();
            $tenant->delete();
            $scope->delete();
        }
    }

}
