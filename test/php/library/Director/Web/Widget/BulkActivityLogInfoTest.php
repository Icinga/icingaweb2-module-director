<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Widget;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Widget\BulkActivityLogInfo;

class BulkActivityLogInfoTest extends BaseTestCase
{
    private const HOST_NAME = '___TEST___db01.example.com';

    public function testSummaryCountsTheSelectedChangesAndSaysWhatRestoreDoes(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $html = $this->renderSummary($this->recordHostChanges(true));

        $this->assertStringContainsString('3 changes selected', $html);
        $this->assertStringContainsString('<span class="activity-badge action-create">1 created</span>', $html);
        $this->assertStringContainsString('<span class="activity-badge action-modify">1 modified</span>', $html);
        $this->assertStringContainsString('<span class="activity-badge action-delete">1 deleted</span>', $html);
        $this->assertStringContainsString('Restoring goes from the newest to the oldest change', $html);

        // A restore turns the change around, so a create gets deleted
        $this->assertStringContainsString('<span class="activity-badge undo-create">1 deleted</span>', $html);
        $this->assertStringContainsString('<span class="activity-badge undo-modify">1 restored</span>', $html);
        $this->assertStringContainsString('<span class="activity-badge undo-delete">1 re-created</span>', $html);
        $this->assertStringContainsString('Object gets deleted', $html);
        $this->assertStringContainsString('Former state gets restored', $html);
        $this->assertStringContainsString('Object gets re-created', $html);
    }

    public function testSummaryRowsLinkToTheirDiff(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        [$deleteId, $createId] = $ids = $this->recordHostChanges(false);
        $html = $this->renderSummary($ids);

        foreach ($ids as $id) {
            // The row and its link in the overview, and the heading they jump to
            $this->assertStringContainsString('<tr href="#activity-' . $id . '"', $html);
            $this->assertStringContainsString('<a href="#activity-' . $id . '"', $html);
            $this->assertStringContainsString('id="activity-' . $id . '"', $html);
        }

        $created = '<h2 id="activity-' . $createId . '" class="icon-plus action-create">';
        $deleted = '<h2 id="activity-' . $deleteId . '" class="icon-cancel action-delete">';
        $this->assertStringContainsString($created, $html);
        $this->assertStringContainsString($deleted, $html);
    }

    public function testSummaryMentionsWhatIsSkipped(): void
    {
        $info = new BulkActivityLogInfo($this->hasDb() ? $this->getDb() : null, []);
        $line = self::callMethod($info, 'createRestoreLine', [
            ['create' => 0, 'modify' => 1, 'delete' => 0],
            2,
        ])->render();

        $this->assertStringContainsString('1 restored', $line);
        $this->assertStringContainsString('<span class="activity-badge undo-skipped">2 skipped</span>', $line);

        $this->assertStringContainsString(
            'None of the selected changes can be restored',
            self::callMethod($info, 'createRestoreLine', [
                ['create' => 0, 'modify' => 0, 'delete' => 0],
                3,
            ])->render()
        );
    }

    /**
     * Create the test host, optionally change it, then delete it
     *
     * @return int[] Newest first, like the controller hands them over
     */
    private function recordHostChanges(bool $withModify): array
    {
        $db = $this->getDb();
        if (IcingaHost::exists(self::HOST_NAME, $db)) {
            IcingaHost::load(self::HOST_NAME, $db)->delete();
        }

        // Earlier runs left entries behind, only look at the new ones
        $dba = $db->getDbAdapter();
        $lastId = (int) $dba->fetchOne($dba->select()->from('director_activity_log', ['MAX(id)']));

        IcingaHost::create([
            'object_name' => self::HOST_NAME,
            'object_type' => 'object',
            'address'     => '192.0.2.20',
        ], $db)->store();

        if ($withModify) {
            $host = IcingaHost::load(self::HOST_NAME, $db);
            $host->set('address', '192.0.2.21');
            $host->store();
        }

        IcingaHost::load(self::HOST_NAME, $db)->delete();

        return $this->entryIdsSince($lastId);
    }

    /**
     * Render the widget without the forms, they need a real request
     *
     * @param int[] $ids
     */
    private function renderSummary(array $ids): string
    {
        return (new class ($this->getDb(), $ids) extends BulkActivityLogInfo {
            protected function createRestoreForm(array $restoreItems)
            {
                return 'restore-form-placeholder';
            }

            protected function createNoteForm(array $ids)
            {
                return 'note-form-placeholder';
            }
        })->render();
    }

    /**
     * @return int[] Newest first, like the controller hands them over
     */
    private function entryIdsSince(int $lastId): array
    {
        $dba = $this->getDb()->getDbAdapter();

        return array_map('intval', $dba->fetchCol(
            $dba->select()
                ->from('director_activity_log', ['id'])
                ->where('object_name = ?', self::HOST_NAME)
                ->where('id > ?', $lastId)
                ->order('id DESC')
        ));
    }

    protected function tearDown(): void
    {
        if ($this->hasDb() && IcingaHost::exists(self::HOST_NAME, $this->getDb())) {
            IcingaHost::load(self::HOST_NAME, $this->getDb())->delete();
        }

        parent::tearDown();
    }
}
