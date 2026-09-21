<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Table;

use Icinga\Authentication\Auth;
use Icinga\Module\Director\Auth\Restriction;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaHostGroup;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Table\ObjectsTableService;

class ObjectsTableServiceRestrictionTest extends BaseTestCase
{
    public function testInheritedServiceIsVisibleOnlyForAnAuthorizedTargetHost(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $dba = $db->getDbAdapter();
        $groupName = '___TEST___allowed_services_3095';
        $auth = $this->getMockBuilder(Auth::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getRestrictions'])
            ->getMock();
        $auth->method('getRestrictions')->willReturnCallback(
            static function ($restriction) use ($groupName) {
                return $restriction === Restriction::FILTER_HOSTGROUPS ? [$groupName] : [];
            }
        );

        $group = IcingaHostGroup::create([
            'object_name' => $groupName,
            'object_type' => 'object',
        ], $db);
        $template = IcingaHost::create([
            'object_name' => '___TEST___service_parent_3095',
            'object_type' => 'template',
        ], $db);
        $allowed = IcingaHost::create([
            'object_name' => '___TEST___allowed_child_3095',
            'object_type' => 'object',
        ], $db);
        $denied = IcingaHost::create([
            'object_name' => '___TEST___denied_child_3095',
            'object_type' => 'object',
        ], $db);

        $group->store();
        $template->store();
        $allowed->store();
        $denied->store();

        $service = IcingaService::create([
            'object_name' => '___TEST___inherited_service_3095',
            'object_type' => 'object',
            'host_id' => $template->get('id'),
        ], $db);
        $service->store();

        try {
            foreach ([$allowed, $denied] as $child) {
                $dba->insert('icinga_host_inheritance', [
                    'host_id' => $child->get('id'),
                    'parent_host_id' => $template->get('id'),
                    'weight' => 1,
                ]);
            }
            $dba->insert('icinga_hostgroup_host_resolved', [
                'hostgroup_id' => $group->get('id'),
                'host_id' => $allowed->get('id'),
            ]);

            $template = IcingaHost::load($template->getObjectName(), $db);
            $allowed = IcingaHost::load($allowed->getObjectName(), $db);
            $denied = IcingaHost::load($denied->getObjectName(), $db);

            $ownServices = new ObjectsTableService($db, $auth);
            $this->assertCount(
                0,
                $dba->fetchAll($ownServices->setHost($template)->getQuery()),
                'The restricted user cannot access the parent template directly'
            );

            $inherited = new ObjectsTableService($db, $auth);
            $inherited->setHost($template)->setInheritedBy($allowed);
            $this->assertCount(
                1,
                $dba->fetchAll($inherited->getQuery()),
                'The restricted user must see inherited services on an allowed host'
            );

            $unauthorized = new ObjectsTableService($db, $auth);
            $unauthorized->setHost($template)->setInheritedBy($denied);
            $this->assertCount(
                0,
                $dba->fetchAll($unauthorized->getQuery()),
                'An inherited-by host outside the allowed group must never expose a parent service'
            );

            // Access to a parent template must not grant access to an unrelated
            // descendant: authorization is always determined by the target host.
            $dba->insert('icinga_hostgroup_host_resolved', [
                'hostgroup_id' => $group->get('id'),
                'host_id' => $template->get('id'),
            ]);
            $parentVisible = new ObjectsTableService($db, $auth);
            $this->assertCount(1, $dba->fetchAll($parentVisible->setHost($template)->getQuery()));
            $unauthorized = new ObjectsTableService($db, $auth);
            $unauthorized->setHost($template)->setInheritedBy($denied);
            $this->assertCount(0, $dba->fetchAll($unauthorized->getQuery()));
        } finally {
            $service->delete();
            $denied->delete();
            $allowed->delete();
            $template->delete();
            $group->delete();
        }
    }
}
