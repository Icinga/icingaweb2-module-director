<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Director\IcingaConfig;

use Icinga\Module\Director\Import\Sync;
use Icinga\Module\Director\Test\SyncTest;

class ImportRunBasedPurgeStrategyTest extends SyncTest
{
    protected $objectType = 'host';

    protected $keyColumn = 'host';

    public function testSameSecondImportRunIsUsedAsPurgeBaseline()
    {
        $this->rule->set('purge_existing', 'y');
        $this->rule->set('purge_action', 'delete');
        $this->rule->store($this->getDb());

        $this->setUpProperty(array(
            'source_expression' => '${host}',
            'destination_field' => 'object_name',
            'priority'          => 10,
        ));

        $this->runImport(array());
        $emptyImportRunId = $this->getLastImportRunId();

        $this->runImport(array(
            array('host' => 'SYNCTEST_same_second_purge')
        ));
        $targetImportRunId = $this->getLastImportRunId();

        $adapter = $this->getDb()->getDbAdapter();
        $baseTimestamp = time() - 30;
        $firstRunTime = date('Y-m-d H:i:s', $baseTimestamp) . '.100000';
        $sameSecondRunTime = date('Y-m-d H:i:s', $baseTimestamp + 1) . '.100000';
        $syncRunTime = date('Y-m-d H:i:s', $baseTimestamp + 1) . '.200000';

        $adapter->update(
            'import_run',
            array('start_time' => $firstRunTime),
            $adapter->quoteInto('id = ?', $emptyImportRunId)
        );
        $adapter->update(
            'import_run',
            array('start_time' => $sameSecondRunTime),
            $adapter->quoteInto('id = ?', $targetImportRunId)
        );

        $this->assertTrue($this->sync->hasModifications());
        $this->assertGreaterThan(0, $this->sync->apply());

        $syncRunId = $this->rule->getLastSyncRunId();
        $adapter->update(
            'sync_run',
            array('start_time' => $syncRunTime),
            $adapter->quoteInto('id = ?', $syncRunId)
        );

        $lastSyncTimestamp = $this->rule->getLastSyncTimestamp();
        $baseline = $this->source->fetchLastRunBefore($lastSyncTimestamp);
        $this->assertNotNull($baseline);
        $this->assertEquals($targetImportRunId, $baseline->get('id'));

        $this->runImport(array());

        $sync = new Sync($this->rule);
        $modifications = $sync->getExpectedModifications();
        $this->assertCount(1, $modifications);
        $this->assertTrue(reset($modifications)->shouldBeRemoved());
    }

    protected function getLastImportRunId()
    {
        $db = $this->getDb()->getDbAdapter();
        $query = $db->select()
            ->from('import_run', array('id'))
            ->where('source_id = ?', $this->source->get('id'))
            ->order('id DESC')
            ->limit(1);

        return $db->fetchOne($query);
    }
}
