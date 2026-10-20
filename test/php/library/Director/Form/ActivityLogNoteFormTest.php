<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Form;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Forms\ActivityLogNoteForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;

class ActivityLogNoteFormTest extends BaseTestCase
{
    private const HOST_NAME = '___TEST___note01.example.com';

    /** @var int[] */
    private $ids = [];

    public function setUp(): void
    {
        parent::setUp();
        if (! $this->hasDb()) {
            return;
        }

        $db = $this->getDb();
        if (IcingaHost::exists(self::HOST_NAME, $db)) {
            IcingaHost::load(self::HOST_NAME, $db)->delete();
        }

        // One create and one modify of the same host
        $dba = $db->getDbAdapter();
        $lastId = (int) $dba->fetchOne($dba->select()->from('director_activity_log', ['MAX(id)']));
        $host = IcingaHost::create([
            'object_name' => self::HOST_NAME,
            'object_type' => 'object',
            'address'     => '192.0.2.30',
        ], $db);
        $host->store();
        $host->set('address', '192.0.2.31');
        $host->store();

        $this->ids = array_map('intval', $dba->fetchCol(
            $dba->select()
                ->from('director_activity_log', ['id'])
                ->where('object_name = ?', self::HOST_NAME)
                ->where('id > ?', $lastId)
                ->order('id')
        ));
    }

    public function testHasANoteFieldWithTheCurrentNote(): void
    {
        $form = new ActivityLogNoteForm($this->createMock(Db::class), [1], 'Ticket OPS-123');
        $form->ensureAssembled();

        $this->assertSame('Ticket OPS-123', $form->getElement('note')->getValue());
        $this->assertNotNull($form->getElement('submit'));
    }

    public function testSingleEntryFieldHasNoLabelOfItsOwn(): void
    {
        $form = new ActivityLogNoteForm($this->createMock(Db::class), [1]);
        $form->ensureAssembled();

        // The detail view has a Note row already, an empty label would only take space
        $this->assertNull($form->getElement('note')->getLabel());
        $this->assertStringNotContainsString('control-label-group', $form->render());
    }

    public function testBulkFieldIsLabeled(): void
    {
        $form = new ActivityLogNoteForm($this->createMock(Db::class), [1, 2]);
        $form->ensureAssembled();

        $this->assertSame('Note', $form->getElement('note')->getLabel());
    }

    public function testSavesOneNoteForEveryGivenEntry(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->storeNotes($this->ids, 'Moved to the new datacenter');

        $this->assertSame(
            [$this->ids[0] => 'Moved to the new datacenter', $this->ids[1] => 'Moved to the new datacenter'],
            $this->getDb()->fetchActivityLogNotes($this->ids)
        );
    }

    public function testChangingANoteReplacesTheOldText(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->storeNotes([$this->ids[0]], 'Ticket OPS-123');
        $this->storeNotes([$this->ids[0]], 'Ticket OPS-456');

        $this->assertSame(
            [$this->ids[0] => 'Ticket OPS-456'],
            $this->getDb()->fetchActivityLogNotes($this->ids)
        );
    }

    public function testAnEmptyNoteRemovesIt(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $this->storeNotes($this->ids, 'Ticket OPS-123');
        $this->storeNotes([$this->ids[1]], '');

        $this->assertSame(
            [$this->ids[0] => 'Ticket OPS-123'],
            $this->getDb()->fetchActivityLogNotes($this->ids)
        );
    }

    public function testRemarksSpanningSeveralEntriesAreNoNotes(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        // A remark covering both entries, it is no note
        $dba = $this->getDb()->getDbAdapter();
        $dba->insert('director_activity_log_remark', [
            'first_related_activity' => $this->ids[0],
            'last_related_activity'  => $this->ids[1],
            'remark'                 => 'Moved all of them to the new datacenter',
        ]);

        $this->assertSame([], $this->getDb()->fetchActivityLogNotes($this->ids));

        $this->storeNotes($this->ids, 'Ticket OPS-123');

        $this->assertSame(
            'Moved all of them to the new datacenter',
            $dba->fetchOne(
                $dba->select()
                    ->from('director_activity_log_remark', 'remark')
                    ->where('first_related_activity = ?', $this->ids[0])
                    ->where('last_related_activity = ?', $this->ids[1])
            )
        );
    }

    public function testEntriesThatAreGoneAreLeftOut(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $form = new ActivityLogNoteForm($this->getDb(), [$this->ids[0], 999999999]);

        $this->assertSame([$this->ids[0]], self::callMethod($form, 'fetchExistingIds', []));
    }

    /**
     * @param int[] $ids
     */
    private function storeNotes(array $ids, string $note): void
    {
        $form = new ActivityLogNoteForm($this->getDb(), $ids);
        self::callMethod($form, 'storeNotes', [$ids, $note]);
    }

    protected function tearDown(): void
    {
        if ($this->hasDb()) {
            $db = $this->getDb();
            $dba = $db->getDbAdapter();
            if (! empty($this->ids)) {
                $dba->delete('director_activity_log_remark', ['first_related_activity IN (?)' => $this->ids]);
            }

            if (IcingaHost::exists(self::HOST_NAME, $db)) {
                IcingaHost::load(self::HOST_NAME, $db)->delete();
            }
        }

        parent::tearDown();
    }
}
