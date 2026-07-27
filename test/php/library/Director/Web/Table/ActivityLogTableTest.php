<?php

namespace Tests\Icinga\Module\Director\Web\Table;

use Icinga\Data\Db\DbConnection;
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

    private function renderContent(ActivityLogTable $table): string
    {
        return implode('', array_map(fn ($element) => $element->render(), $table->getContent()));
    }
}
