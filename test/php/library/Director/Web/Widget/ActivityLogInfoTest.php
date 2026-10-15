<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Widget;

use gipfl\IcingaWeb2\Url;
use Icinga\Module\Director\Objects\DirectorActivityLog;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\RestApi\CustomVarApplyRequest;
use Icinga\Module\Director\RestApi\CustomVariableValueApplier;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Widget\ActivityLogInfo;

class ActivityLogInfoTest extends BaseTestCase
{
    private const HOST_NAME = '___TEST___web01.example.com';
    private const RESTORE_MARKER = 'restore-form-placeholder';

    public function testVarsOnlyRestModificationEntryCanBeOpened(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createHost($db);

        (new CustomVariableValueApplier($db))->apply(new CustomVarApplyRequest(
            IcingaHost::load(self::HOST_NAME, $db),
            ['os' => 'Debian'],
            'host',
            'POST',
            true
        ));

        $id = $this->latestEntryId();
        $info = $this->infoFor($id);
        $html = $info->showTab(null)->render();
        $this->assertStringContainsString('Debian', $html);
        $this->assertStringContainsString(self::RESTORE_MARKER, $html);

        $dba = $db->getDbAdapter();
        $stored = json_decode($dba->fetchOne(
            $dba->select()->from('director_activity_log', ['old_properties'])->where('id = ?', $id)
        ));
        $this->assertSame(self::HOST_NAME, $stored->object_name);
        $this->assertSame('192.0.2.10', $stored->address);

        // Restoring must only bring the variables back, not blank anything else
        $host = IcingaHost::load(self::HOST_NAME, $db);
        $host->replaceWith($info->former());
        $this->assertSame('192.0.2.10', $host->get('address'));
        $this->assertSame('object', $host->get('object_type'));
    }

    public function testLegacyVarsOnlyEntryOpensWithoutRestoreForm(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $this->createHost($db);

        // What 1.12.0 wrote for a vars-only change, no name and no other properties
        $data = [
            'object_name'     => self::HOST_NAME,
            'action_name'     => DirectorActivityLog::ACTION_MODIFY,
            'author'          => 'tester',
            'object_type'     => 'icinga_host',
            'old_properties'  => json_encode(['vars' => ['os' => 'Ubuntu']]),
            'new_properties'  => json_encode(['vars' => ['os' => 'Debian']]),
            'change_time'     => date('Y-m-d H:i:s'),
            'parent_checksum' => $db->getLastActivityChecksum(),
        ];
        $data['checksum'] = sha1(json_encode($data), true);
        $data['parent_checksum'] = hex2bin($data['parent_checksum']);
        DirectorActivityLog::create($data)->store($db);

        $html = $this->infoFor($this->latestEntryId())->showTab(null)->render();
        $this->assertStringContainsString('Debian', $html);
        $this->assertStringNotContainsString(self::RESTORE_MARKER, $html);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb() && IcingaHost::exists(self::HOST_NAME, $this->getDb())) {
            IcingaHost::load(self::HOST_NAME, $this->getDb())->delete();
        }

        parent::tearDown();
    }

    private function latestEntryId(): int
    {
        $dba = $this->getDb()->getDbAdapter();

        return (int) $dba->fetchOne(
            $dba->select()
                ->from('director_activity_log', ['id'])
                ->where('object_name = ?', self::HOST_NAME)
                ->order('id DESC')
                ->limit(1)
        );
    }

    private function infoFor(int $id)
    {
        // The real restore form needs the whole module loaded, use a stand-in
        $info = (new class (
            $this->getDb(),
            'icinga_host',
            self::HOST_NAME,
            self::RESTORE_MARKER
        ) extends ActivityLogInfo {
            private $marker;

            public function __construct($db, $type, $name, $marker)
            {
                parent::__construct($db, $type, $name);
                $this->marker = $marker;
            }

            protected function getRestoreForm()
            {
                return $this->marker;
            }

            public function former()
            {
                return $this->oldObject();
            }
        })->setId($id);
        $info->getTabs(Url::fromPath('director/config/activity', ['id' => $id]));

        return $info;
    }

    private function createHost($db): void
    {
        if (IcingaHost::exists(self::HOST_NAME, $db)) {
            IcingaHost::load(self::HOST_NAME, $db)->delete();
        }

        IcingaHost::create([
            'object_name' => self::HOST_NAME,
            'object_type' => 'object',
            'address'     => '192.0.2.10',
            'vars'        => ['os' => 'Ubuntu'],
        ], $db)->store();
    }
}
