<?php

// SPDX-FileCopyrightText: 2026 Daniel Vedovato
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Objects;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaTemplateChoiceHost;
use Icinga\Module\Director\Test\BaseTestCase;

class IcingaTemplateChoiceActivityLogTest extends BaseTestCase
{
    private const TEMPLATE_A = '___TEST___activity_choice_host_a';
    private const TEMPLATE_B = '___TEST___activity_choice_host_b';
    private const CHOICE = '___TEST___activity_choice';

    public function testMemberOnlyModificationIsRecordedInActivityLog(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $first = IcingaHost::create([
            'object_name' => self::TEMPLATE_A,
            'object_type' => 'template',
        ], $db);
        $first->store();

        IcingaHost::create([
            'object_name' => self::TEMPLATE_B,
            'object_type' => 'template',
        ], $db)->store();

        $choice = IcingaTemplateChoiceHost::create([
            'object_name' => self::CHOICE,
            'required_template_id' => $first->get('id'),
        ], $db);
        $choice->setMembers([self::TEMPLATE_A]);
        $choice->store();

        $choice->setMembers([self::TEMPLATE_A, self::TEMPLATE_B]);
        $this->assertSame([self::TEMPLATE_A], $choice->getPlainUnmodifiedObject()->members);
        $this->assertSame(
            [self::TEMPLATE_A, self::TEMPLATE_B],
            json_decode($choice->toJson(null, true), true)['members']
        );
        $choice->store();

        $adapter = $db->getDbAdapter();
        $entry = $adapter->fetchRow(
            $adapter->select()
                ->from('director_activity_log', ['old_properties', 'new_properties'])
                ->where('object_type = ?', 'icinga_host_template_choice')
                ->where('object_name = ?', self::CHOICE)
                ->where('action_name = ?', 'modify')
                ->order('id DESC')
                ->limit(1)
        );

        $this->assertNotFalse($entry, 'Changing only members must create an activity entry');
        $this->assertSame(
            [self::TEMPLATE_A],
            json_decode($entry->old_properties, true)['members']
        );
        $this->assertSame(
            [self::TEMPLATE_A, self::TEMPLATE_B],
            json_decode($entry->new_properties, true)['members']
        );
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            if (IcingaTemplateChoiceHost::exists(self::CHOICE, $db)) {
                IcingaTemplateChoiceHost::load(self::CHOICE, $db)->delete();
            }
            foreach ([self::TEMPLATE_A, self::TEMPLATE_B] as $name) {
                if (IcingaHost::exists($name, $db)) {
                    IcingaHost::load($name, $db)->delete();
                }
            }
        }

        parent::tearDown();
    }
}
