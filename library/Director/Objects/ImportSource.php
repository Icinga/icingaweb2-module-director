<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Benchmark;
use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Import\Import;
use Exception;

class ImportSource extends DbObjectWithSettings
{
    protected $table = 'import_source';

    protected $keyName = 'id';

    protected $autoincKeyName = 'id';

    protected $defaultProperties = array(
        'id'                 => null,
        'source_name'        => null,
        'provider_class'     => null,
        'key_column'         => null,
        'import_state'       => 'unknown',
        'last_error_message' => null,
        'last_attempt'       => null,
    );

    protected $settingsTable = 'import_source_setting';

    protected $settingsRemoteId = 'source_id';

    public function fetchLastRun()
    {
        return $this->fetchLastRunBefore(time());
    }

    public function fetchLastRunBefore($timestamp)
    {
        if (! $this->hasBeenLoadedFromDb()) {
            return null;
        }

        if ($timestamp === null) {
            $timestamp = time();
        }

        $db = $this->getDb();
        $query = $db->select()->from(
            array('ir' => 'import_run'),
            'ir.id'
        )->where('ir.source_id = ?', $this->id)
        ->where('ir.start_time < ?', date('Y-m-d H:i:s', $timestamp))
        ->order('ir.start_time DESC')
        ->limit(1);

        $runId = $db->fetchOne($query);

        if ($runId) {
            return ImportRun::load($runId, $this->getConnection());
        } else {
            return null;
        }
    }

    public function fetchRowModifiers()
    {
        $db = $this->getDb();
        return ImportRowModifier::loadAll(
            $this->getConnection(),
            $db->select()
               ->from('import_row_modifier')
               ->where('source_id = ?', $this->id)
               ->order('priority DESC')
        );
    }

    public function checkForChanges($runImport = false)
    {
        $hadChanges = false;

        Benchmark::measure('Starting with import ' . $this->source_name);
        try {
            $import = new Import($this);
            if ($import->providesChanges()) {
                Benchmark::measure('Found changes for ' . $this->source_name);
                $this->hadChanges = true;
                $this->import_state = 'pending-changes';

                if ($runImport && $import->run()) {
                    Benchmark::measure('Import succeeded for ' . $this->source_name);
                    $this->import_state = 'in-sync';
                }
            } else {
                $this->import_state = 'in-sync';
            }

            $this->last_error_message = null;

        } catch (Exception $e) {
            $this->import_state = 'failing';
            Benchmark::measure('Import failed for ' . $this->source_name);
            $this->last_error_message = 'ERR: ' . $e->getMessage();
        }

        if ($this->hasBeenModified()) {
            $this->store();
        }

        return $hadChanges;
    }

    public function runImport()
    {
        return $this->checkForChanges(true);
    }
}
