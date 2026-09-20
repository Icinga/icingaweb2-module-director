<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\Web\Navigation\Renderer;

use Icinga\Module\Director\Web\Navigation\Renderer\ImportPendingNavigationItemRenderer;
use Icinga\Module\Director\Test\BaseTestCase;

class ImportPendingNavigationItemRendererTest extends BaseTestCase
{
    /**
     * Counts distinct import sources with pending changes, not individual
     * import rows, and does not count failed or synchronized sources.
     */
    public function testBadgeCountsPendingImportSourcesAndClearsAfterImport(): void
    {
        if ($this->skipForMissingDb()) {
            return;
        }

        $db = $this->getDb();
        $adapter = $db->getDbAdapter();
        $baseline = (int) $adapter->fetchOne(
            "SELECT COUNT(*) FROM import_source WHERE import_state = 'pending-changes'"
        );
        $names = [
            '___TEST___3070_pending_first',
            '___TEST___3070_pending_second',
            '___TEST___3070_import_failing',
        ];
        try {
            foreach ($names as $i => $name) {
                $adapter->insert('import_source', [
                    'source_name' => $name,
                    'key_column' => 'name',
                    'provider_class' => 'test',
                    'import_state' => $i === 2 ? 'failing' : 'pending-changes',
                ]);
            }

            $pending = $this->newRenderer($db);
            $this->assertTrue($pending->hasProblems());
            $this->assertSame($baseline + 2, $pending->getCount());
            $this->assertSame($pending::STATE_WARNING, $pending->getState());
            $this->assertStringContainsString(
                (string) ($baseline + 2),
                (string) $pending->getTitle()
            );

            $adapter->update(
                'import_source',
                ['import_state' => 'in-sync'],
                $adapter->quoteInto('source_name = ?', $names[0])
            );
            $this->assertSame($baseline + 1, $this->newRenderer($db)->getCount());

            $adapter->update(
                'import_source',
                ['import_state' => 'in-sync'],
                $adapter->quoteInto('source_name = ?', $names[1])
            );
            $cleared = $this->newRenderer($db);
            $this->assertSame($baseline, $cleared->getCount());
            if ($baseline === 0) {
                $this->assertFalse($cleared->hasProblems());
                $this->assertSame($cleared::STATE_OK, $cleared->getState());
            }
        } finally {
            $adapter->delete('import_source', [
                $adapter->quoteInto('source_name IN (?)', $names),
            ]);
        }
    }

    private function newRenderer($db): ImportPendingNavigationItemRenderer
    {
        return new class ($db) extends ImportPendingNavigationItemRenderer {
            private $connection;

            public function __construct($db)
            {
                $this->connection = $db;
            }

            protected function db()
            {
                return $this->connection;
            }
        };
    }
}
