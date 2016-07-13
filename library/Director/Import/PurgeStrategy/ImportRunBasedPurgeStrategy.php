<?php

namespace Icinga\Module\Director\Import\PurgeStrategy;

use Icinga\Module\Director\Import\SyncUtils;
use Icinga\Module\Director\Objects\ImportRun;
use Icinga\Module\Director\Objects\ImportSource;

class ImportRunBasedPurgeStrategy extends PurgeStrategy
{
    public function listObjectsToPurge()
    {
        $remove = array();

        foreach ($this->getSyncRule()->fetchInvolvedImportSources() as $source) {
            $remove += $this->checkImportSource($source);
        }

        return $remove;
    }

    protected function getLastSync()
    {
        return strtotime($this->getSyncRule()->getLastSyncTimestamp());
    }

    // TODO: NAMING!
    protected function checkImportSource(ImportSource $source)
    {
        if (null === ($lastSync = $this->getLastSync())) {
            // No last sync, nothing to purge
            return array();
        }

        $runA = $source->fetchLastRunBefore($lastSync);
        if ($runA === null) {
            // Nothing to purge for this source
            return array();
        }

        $runB = $source->fetchLastRun();
        if ($runA->rowset_checksum === $runB->rowset_checksum) {
            // Same source data, nothing to purge
            return array();
        }

        return $this->listKeysRemovedBetween($runA, $runB);
    }

    public function listKeysRemovedBetween(ImportRun $runA, ImportRun $runB)
    {
        $rule = $this->getSyncRule();
        $db = $rule->getDb();

        $selectA = $runA->prepareImportedObjectQuery();
        $selectB = $runB->prepareImportedObjectQuery();

        $query = $db->select()->from(
            array('a' => $selectA),
            'a.object_name'
        )->joinLeft(
            array('b' => $selectB),
            'a.object_name = b.object_name',
            array()
        )->where('b.object_name IS NULL');

        $result = $db->fetchCol($query);

        if ($rule->object_type === 'service') {
            $pattern = $rule->getSourceKeyPattern();
            $columns = SyncUtils::extractVariableNames($pattern);
            $rows = $runA->fetchRows($columns, null, $result);
            $result = array();
            foreach ($rows as $row) {
                $result[] = SyncUtils::fillVariables($pattern, $row);
            }
        }

        if ($result) {
            return array_combine($result, $result);
        } else {
            return array();
        }
    }
}
