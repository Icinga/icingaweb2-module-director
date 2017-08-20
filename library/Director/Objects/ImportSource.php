<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Benchmark;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Import\Import;
use Icinga\Module\Director\Import\SyncUtils;
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
        'description'        => null,
    );

    protected $settingsTable = 'import_source_setting';

    protected $settingsRemoteId = 'source_id';

    private $rowModifiers;

    /**
     * @param bool $required
     * @return ImportRun|null
     */
    public function fetchLastRun($required = false)
    {
        return $this->fetchLastRunBefore(time() + 1, $required);
    }

    /**
     * @param $timestamp
     * @param bool $required
     * @return ImportRun|null
     */
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

    public function applyModifiers(& $data)
    {
        $modifiers = $this->getRowModifiers();

        if (! empty($modifiers)) {
            foreach ($data as &$row) {
                $this->applyModifiersToRow($row);
            }
        }

        return $this;
    }

    public function applyModifiersToRow(& $row)
    {
        $modifiers = $this->getRowModifiers();

        foreach ($modifiers as $key => $mods) {
            /** @var PropertyModifierHook $mod */
            foreach ($mods as $mod) {
                if ($mod->requiresRow()) {
                    $mod->setRow($row);
                }
                if (! property_exists($row, $key)) {
                    // Partial support for nested keys. Must write result to
                    // a dedicated flat key
                    if (strpos($key, '.') !== false) {
                        $val = SyncUtils::getSpecificValue($row, $key);
                        if ($val !== null) {
                            $target = $mod->getTargetProperty($key);
                            if (strpos($target, '.') !== false) {
                                throw new ConfigurationError(
                                    'Cannot set value for nested key "%s"',
                                    $target
                                );
                            }

                            $row->$target = $mod->transform($val);
                        }
                    }

                    continue;
                }

                $target = $mod->getTargetProperty($key);

                if (is_array($row->$key) && ! $mod->hasArraySupport()) {
                    $new = array();
                    foreach ($row->$key as $k => $v) {
                        $new[$k] = $mod->transform($v);
                    }
                    $row->$target = $new;
                } else {
                    $row->$target = $mod->transform($row->$key);
                }
            }
        }

        return $this;
    }

    public function getRowModifiers()
    {
        if ($this->rowModifiers === null) {
            $this->prepareRowModifiers();
        }

        return $this->rowModifiers;
    }

    public function hasRowModifiers()
    {
        return count($this->getRowModifiers()) > 0;
    }

    public function fetchRowModifiers()
    {
        $db = $this->getDb();

        $modifiers = ImportRowModifier::loadAll(
            $this->getConnection(),
            $db->select()
               ->from('import_row_modifier')
               ->where('source_id = ?', $this->id)
               ->order('priority ASC')
        );

        return $modifiers;
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

    public function listModifierTargetProperties()
    {
        $list = array();
        foreach ($this->getRowModifiers() as $rowMods) {
            /** @var PropertyModifierHook $mod */
            foreach ($rowMods as $mod) {
                if ($mod->hasTargetProperty()) {
                    $list[$mod->getTargetProperty()] = true;
                }
            }
        }

        return array_keys($list);
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
