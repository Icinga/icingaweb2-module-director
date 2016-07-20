<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Benchmark;
use Icinga\Exception\NotFoundError;
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

    private $rowModifiers;

    public function fetchLastRun($required = false)
    {
        return $this->fetchLastRunBefore(time() + 1, $required);
    }

    public function fetchLastRunBefore($timestamp, $required = false)
    {
        if (! $this->hasBeenLoadedFromDb()) {
            return $this->nullUnlessRequired($required);
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
            return $this->nullUnlessRequired($required);
        }
    }

    protected function nullUnlessRequired($required)
    {
        if ($required) {
            throw new NotFoundError(
                'No data has been imported for "%s" yet',
                $this->source_name
            );
        }

        return null;
    }

    public function getRowModifiers()
    {
        if ($this->rowModifiers === null) {
            $this->prepareRowModifiers();
        }

        return $this->rowModifiers;
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

    protected function prepareRowModifiers()
    {
        $modifiers = array();

        foreach ($this->fetchRowModifiers() as $mod) {
            if (! array_key_exists($mod->property_name, $modifiers)) {
                $modifiers[$mod->property_name] = array();
            }

            $modifiers[$mod->property_name][] = $mod->getInstance();
        }

        $this->rowModifiers = $modifiers;
    }

    public function checkForChanges($runImport = false)
    {
        $hadChanges = false;

        Benchmark::measure('Starting with import ' . $this->source_name);
        try {
            $import = new Import($this);
            $this->last_attempt = date('Y-m-d H:i:s');
            if ($import->providesChanges()) {
                Benchmark::measure('Found changes for ' . $this->source_name);
                $hadChanges = true;
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
            $this->last_error_message = $e->getMessage();
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
