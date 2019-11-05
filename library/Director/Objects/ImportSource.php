<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Application\Benchmark;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Application\MemoryLimit;
use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Exception\DuplicateKeyException;
use Icinga\Module\Director\Hook\PropertyModifierHook;
use Icinga\Module\Director\Import\Import;
use Icinga\Module\Director\Import\SyncUtils;
use InvalidArgumentException;
use Exception;

class ImportSource extends DbObjectWithSettings implements ExportInterface
{
    protected $table = 'import_source';

    protected $keyName = 'source_name';

    protected $autoincKeyName = 'id';

    protected $protectAutoinc = false;

    protected $defaultProperties = [
        'id'                 => null,
        'source_name'        => null,
        'provider_class'     => null,
        'key_column'         => null,
        'import_state'       => 'unknown',
        'last_error_message' => null,
        'last_attempt'       => null,
        'description'        => null,
    ];

    protected $stateProperties = [
        'import_state',
        'last_error_message',
        'last_attempt',
    ];

    protected $settingsTable = 'import_source_setting';

    protected $settingsRemoteId = 'source_id';

    private $rowModifiers;

    private $loadedRowModifiers;

    private $newRowModifiers;

    /**
     * @return \stdClass
     */
    public function export()
    {
        $plain = $this->getProperties();
        $plain['originalId'] = $plain['id'];
        unset($plain['id']);

        foreach ($this->stateProperties as $key) {
            unset($plain[$key]);
        }

        $plain['settings'] = (object) $this->getSettings();
        $plain['modifiers'] = $this->exportRowModifiers();
        ksort($plain);

        return (object) $plain;
    }

    /**
     * @param $plain
     * @param Db $db
     * @param bool $replace
     * @return ImportSource
     * @throws DuplicateKeyException
     * @throws NotFoundError
     */
    public static function import($plain, Db $db, $replace = false)
    {
        $properties = (array) $plain;
        if (isset($properties['originalId'])) {
            $id = $properties['originalId'];
            unset($properties['originalId']);
        } else {
            $id = null;
        }
        $name = $properties['source_name'];

        if ($replace && static::existsWithNameAndId($name, $id, $db)) {
            $object = static::loadWithAutoIncId($id, $db);
        } elseif ($replace && static::exists($name, $db)) {
            $object = static::load($name, $db);
        } elseif (static::existsWithName($name, $db)) {
            throw new DuplicateKeyException(
                'Import Source %s already exists',
                $name
            );
        } else {
            $object = static::create([], $db);
        }

        $object->setProperties($properties);
        if ($id !== null) {
            // TODO: really?
            $object->reallySet('id', $id);
        }

        return $object;
    }

    public function setModifiers(array $modifiers)
    {
        if ($this->loadedRowModifiers === null && $this->hasBeenLoadedFromDb()) {
            $this->loadedRowModifiers = $this->fetchRowModifiers();
        }
        $current = $this->loadedRowModifiers;
        if ($current !== null && count($current) !== count($modifiers)) {
            $this->newRowModifiers = $modifiers;
        } else {
            $i = 0;
            $modified = false;
            foreach ($modifiers as $props) {
                $this->loadedRowModifiers[$i]->setProperties((array) $props);
                if ($this->loadedRowModifiers[$i]->hasBeenModified()) {
                    $modified = true;
                }
            }
            if ($modified) {
                // TOOD: no newRowModifiers, directly store loaded ones if diff
                $this->newRowModifiers = $modifiers;
            }
        }
    }

    public function hasBeenModified()
    {
        return $this->newRowModifiers !== null
            || parent::hasBeenModified();
    }

    public function getUniqueIdentifier()
    {
        return $this->get('source_name');
    }

    /**
     * @param $name
     * @param Db $connection
     * @return ImportSource
     * @throws NotFoundError
     */
    public static function loadByName($name, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $properties = $db->fetchRow(
            $db->select()->from('import_source')->where('source_name = ?', $name)
        );
        if ($properties === false) {
            throw new NotFoundError(sprintf(
                'There is no such Import Source: "%s"',
                $name
            ));
        }

        return static::create([], $connection)->setDbProperties($properties);
    }

    public static function existsWithName($name, Db $connection)
    {
        $db = $connection->getDbAdapter();

        return (string) $name === (string) $db->fetchOne(
            $db->select()
                ->from('import_source', 'source_name')
                ->where('source_name = ?', $name)
        );
    }

    /**
     * @param string $name
     * @param int $id
     * @param Db $connection
     * @api internal
     * @return bool
     */
    protected static function existsWithNameAndId($name, $id, Db $connection)
    {
        $db = $connection->getDbAdapter();
        $dummy = new static;
        $idCol = $dummy->autoincKeyName;
        $keyCol = $dummy->keyName;

        return (string) $id === (string) $db->fetchOne(
            $db->select()
                ->from($dummy->table, $idCol)
                ->where("$idCol = ?", $id)
                ->where("$keyCol = ?", $name)
        );
    }

    protected function exportRowModifiers()
    {
        $modifiers = [];
        foreach ($this->fetchRowModifiers() as $modifier) {
            $modifiers[] = $modifier->export();
        }

        return $modifiers;
    }

    /**
     * @param bool $required
     * @return ImportRun|null
     * @throws NotFoundError
     */
    public function fetchLastRun($required = false)
    {
        return $this->fetchLastRunBefore(time() + 1, $required);
    }

    /**
     * @throws DuplicateKeyException
     */
    protected function onStore()
    {
        parent::onStore();
        if ($this->newRowModifiers !== null) {
            $connection = $this->getConnection();
            $db = $connection->getDbAdapter();
            $myId = $this->get('id');
            if ($this->hasBeenLoadedFromDb()) {
                $db->delete(
                    'import_row_modifier',
                    $db->quoteInto('source_id = ?', $myId)
                );
            }

            foreach ($this->newRowModifiers as $modifier) {
                $modifier = ImportRowModifier::create((array) $modifier, $connection);
                $modifier->set('source_id', $myId);
                $modifier->store();
            }
        }
    }

    /**
     * @param $timestamp
     * @param bool $required
     * @return ImportRun|null
     * @throws NotFoundError
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
            ['ir' => 'import_run'],
            'ir.id'
        )->where('ir.source_id = ?', $this->get('id'))
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

    /**
     * @param $required
     * @return null
     * @throws NotFoundError
     */
    protected function nullUnlessRequired($required)
    {
        if ($required) {
            throw new NotFoundError(
                'No data has been imported for "%s" yet',
                $this->get('source_name')
            );
        }

        return null;
    }

    public function applyModifiers(& $data)
    {
        $modifiers = $this->fetchFlatRowModifiers();

        if (empty($modifiers)) {
            return $this;
        }


        foreach ($modifiers as $modPair) {
            /** @var PropertyModifierHook $modifier */
            list($property, $modifier) = $modPair;
            $rejected = [];
            foreach ($data as $key => $row) {
                $this->applyPropertyModifierToRow($modifier, $property, $row);
                if ($modifier->rejectsRow()) {
                    $rejected[] = $key;
                    $modifier->rejectRow(false);
                }
            }

            foreach ($rejected as $key) {
                unset($data[$key]);
            }
        }

        return $this;
    }

    public function getObjectName()
    {
        return $this->get('source_name');
    }

    public static function getKeyColumnName()
    {
        return 'source_name';
    }

    protected function applyPropertyModifierToRow(PropertyModifierHook $modifier, $key, $row)
    {
        if (! is_object($row)) {
            throw new InvalidArgumentException('Every imported row MUST be an object');
        }
        if ($modifier->requiresRow()) {
            $modifier->setRow($row);
        }

        if (property_exists($row, $key)) {
            $value = $row->$key;
        } elseif (strpos($key, '.') !== false) {
            $value = SyncUtils::getSpecificValue($row, $key);
        } else {
            $value = null;
        }

        $target = $modifier->getTargetProperty($key);
        if (strpos($target, '.') !== false) {
            throw new InvalidArgumentException(sprintf(
                'Cannot set value for nested key "%s"',
                $target
            ));
        }

        if (is_array($value) && ! $modifier->hasArraySupport()) {
            $new = [];
            foreach ($value as $k => $v) {
                $new[$k] = $modifier->transform($v);
            }
            $row->$target = $new;
        } else {
            $row->$target = $modifier->transform($value);
        }
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

    /**
     * @return ImportRowModifier[]
     */
    public function fetchRowModifiers()
    {
        $db = $this->getDb();
        $modifiers = ImportRowModifier::loadAll(
            $this->getConnection(),
            $db->select()
               ->from('import_row_modifier')
               ->where('source_id = ?', $this->get('id'))
               ->order('priority ASC')
        );

        if ($modifiers) {
            return $modifiers;
        } else {
            return [];
        }
    }

    protected function fetchFlatRowModifiers()
    {
        $mods = [];
        foreach ($this->fetchRowModifiers() as $mod) {
            $mods[] = [$mod->get('property_name'), $mod->getInstance()];
        }

        return $mods;
    }

    protected function prepareRowModifiers()
    {
        $modifiers = [];

        foreach ($this->fetchRowModifiers() as $mod) {
            $name = $mod->get('property_name');
            if (! array_key_exists($name, $modifiers)) {
                $modifiers[$name] = [];
            }

            $modifiers[$name][] = $mod->getInstance();
        }

        $this->rowModifiers = $modifiers;
    }

    public function listModifierTargetProperties()
    {
        $list = [];
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

    /**
     * @param bool $runImport
     * @return bool
     * @throws DuplicateKeyException
     */
    public function checkForChanges($runImport = false)
    {
        $hadChanges = false;

        $name = $this->get('source_name');
        Benchmark::measure("Starting with import $name");
        $this->raiseLimits();
        try {
            $import = new Import($this);
            $this->set('last_attempt', date('Y-m-d H:i:s'));
            if ($import->providesChanges()) {
                Benchmark::measure("Found changes for $name");
                $hadChanges = true;
                $this->set('import_state', 'pending-changes');

                if ($runImport && $import->run()) {
                    Benchmark::measure("Import succeeded for $name");
                    $this->set('import_state', 'in-sync');
                }
            } else {
                $this->set('import_state', 'in-sync');
            }

            $this->set('last_error_message', null);
        } catch (Exception $e) {
            $this->set('import_state', 'failing');
            Benchmark::measure("Import failed for $name");
            $this->set('last_error_message', $e->getMessage());
        }

        if ($this->hasBeenModified()) {
            $this->store();
        }

        return $hadChanges;
    }

    /**
     * @return bool
     * @throws DuplicateKeyException
     */
    public function runImport()
    {
        return $this->checkForChanges(true);
    }

    /**
     * Raise PHP resource limits
     *
     * @return $this;
     */
    protected function raiseLimits()
    {
        MemoryLimit::raiseTo('1024M');
        ini_set('max_execution_time', 0);

        return $this;
    }
}
