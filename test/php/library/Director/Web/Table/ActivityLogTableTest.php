<?php

namespace Tests\Icinga\Module\Director\Web\Table;

use Icinga\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Test\BaseTestCase;
use Icinga\Module\Director\Web\Table\ActivityLogTable;

class ActivityLogTableTest extends BaseTestCase
{
    public function testDefaultsToRenderingWithoutThePdfExportClass()
    {
        $table = new ActivityLogTable($this->createMock(DbConnection::class));

        self::callMethod($table, 'assemble', []);

        $class = (string) $table->getAttributes()->get('class')->renderValue();
        $this->assertStringNotContainsString('pdf-export', $class);
    }

    public function testAddsThePdfExportClassWhenRenderingForPdf()
    {
        $table = new ActivityLogTable($this->createMock(DbConnection::class));
        $table->setIsPdfExport(true);

        self::callMethod($table, 'assemble', []);

        $class = (string) $table->getAttributes()->get('class')->renderValue();
        $this->assertStringContainsString('pdf-export', $class);
    }

    public function testPdfExportStartsWithOneEmptySpacerBeforeTheFirstDay()
    {
        $table = new ActivityLogTable($this->createMock(DbConnection::class));
        $table->setIsPdfExport(true);

        self::callMethod($table, 'renderDayIfNew', [strtotime('2026-10-15 10:00:00 UTC')]);
        self::callMethod($table, 'renderDayIfNew', [strtotime('2026-10-12 07:40:00 UTC')]);

        $html = $this->renderContent($table);

        $this->assertSame(1, substr_count($html, 'pdf-page-spacer'));
        $this->assertLessThan(strpos($html, 'table-header-day'), strpos($html, 'pdf-page-spacer'));
        $this->assertSame(2, substr_count($html, 'table-header-day'));
    }

    public function testScreenViewHasNoSpacer()
    {
        $table = new ActivityLogTable($this->createMock(DbConnection::class));

        self::callMethod($table, 'renderDayIfNew', [strtotime('2026-10-15 10:00:00 UTC')]);

        $this->assertStringNotContainsString('pdf-page-spacer', $this->renderContent($table));
    }

    public function testLoadsNotesOnSingleEntriesButNotRangeRemarks(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $name = '___TEST___note02.example.com';
        if (IcingaHost::exists($name, $db)) {
            IcingaHost::load($name, $db)->delete();
        }

        $host = IcingaHost::create(['object_name' => $name, 'object_type' => 'object'], $db);
        $host->store();
        $host->set('address', '192.0.2.40');
        $host->store();

        $dba = $db->getDbAdapter();
        $ids = array_map('intval', $dba->fetchCol(
            $dba->select()->from('director_activity_log', ['id'])->where('object_name = ?', $name)->order('id')
        ));
        $dba->insert('director_activity_log_remark', [
            'first_related_activity' => $ids[0],
            'last_related_activity'  => $ids[0],
            'remark'                 => 'Ticket OPS-123',
        ]);
        $dba->insert('director_activity_log_remark', [
            'first_related_activity' => $ids[0],
            'last_related_activity'  => $ids[1],
            'remark'                 => 'Moved all of them to the new datacenter',
        ]);

        try {
            $table = (new ActivityLogTable($dba))->filterObject('icinga_host', $name);
            self::callMethod($table, 'fetchQueryRows', []);
            $notes = (new \ReflectionProperty($table, 'notes'))->getValue($table);
            $ranges = (new \ReflectionProperty($table, 'ranges'))->getValue($table);

            $this->assertSame([$ids[0] => 'Ticket OPS-123'], $notes);
            $this->assertCount(1, $ranges);
            $this->assertSame('Moved all of them to the new datacenter', $ranges[0]->remark);
        } finally {
            $dba->delete('director_activity_log_remark', ['first_related_activity = ?' => $ids[0]]);
            IcingaHost::load($name, $db)->delete();
        }
    }

    public function testNoteLineKeepsTheFullTextInTheTooltip(): void
    {
        $table = new ActivityLogTable($this->createMock(DbConnection::class));

        $html = self::callMethod($table, 'makeNote', ['Ticket OPS-123'])->render();

        $this->assertSame('<div class="activity-note" title="Ticket OPS-123">Ticket OPS-123</div>', $html);
    }

    private function renderContent(ActivityLogTable $table): string
    {
        return implode('', array_map(fn ($element) => $element->render(), $table->getContent()));
    }
}
