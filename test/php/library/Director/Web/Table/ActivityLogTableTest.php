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
}
