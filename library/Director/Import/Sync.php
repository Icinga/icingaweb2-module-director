<?php

namespace Icinga\Module\Director\Import;

use Exception;
use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Application\MemoryLimit;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Data\Db\DbObjectStore;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\Branch\BranchSupport;
use Icinga\Module\Director\Db\Cache\PrefetchCache;
use Icinga\Module\Director\Objects\HostGroupMembershipResolver;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaHostGroup;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\SyncProperty;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Module\Director\Objects\SyncRun;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\Repository\IcingaTemplateRepository;
use InvalidArgumentException;
use RuntimeException;

class Sync
{
    /** @var SyncRule */
    protected $rule;

    /** @var Db */
    protected $db;

    /** @var array Related ImportSource objects */
    protected $sources;

    /** @var array Source columns we want to fetch from our sources */
    protected $sourceColumns;

    /** @var array Imported data */
    protected $imported;

    /** @var IcingaObject[] Objects to work with */
    protected $objects;

    /** @var array<mixed, array<int, string>> key => [property, property]*/
    protected $setNull = [];

    /** @var bool Whether we already prepared your sync */
    protected $isPrepared = false;

    /** @var bool Whether we applied strtolower() to existing object keys */
    protected $usedLowerCasedKeys = false;

    protected $modify = [];

    protected $remove = [];

    protected $create = [];

    protected $errors = [];

    /** @var SyncProperty[] */
    protected $syncProperties;

    protected $replaceVars = false;

    protected $hasPropertyDisabled = false;

    protected $serviceOverrideKeyName;

    /**
     * @var SyncRun
     */
    protected $run;

    protected $runStartTime;

    /** @var Filter[] */
    protected $columnFilters = [];

    /** @var HostGroupMembershipResolver|bool */
    protected $hostGroupMembershipResolver;

    /** @var ?DbObjectStore */
    protected $store;

    /**
     * @param SyncRule $rule
     * @param ?DbObjectStore $store
     */
    public function __construct(SyncRule $rule, DbObjectStore $store = null)
    {
        $this->rule = $rule;
        $this->db = $rule->getConnection();
        $this->store = $store;
    }

    /**
     * Whether the given sync rule would apply modifications
     *
     * @return boolean
     * @throws Exception
     */
    public function hasModifications()
    {
        return count($this->getExpectedModifications()) > 0;
    }

    /**
     * Retrieve modifications a given SyncRule would apply
     *
     * @return array  Array of IcingaObject elements
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function getExpectedModifications()
    {
        $modified = [];
        $objects = $this->prepare();
        $updateOnly = $this->rule->get('update_policy') === 'update-only';
        $allowCreate = ! $updateOnly;
        foreach ($objects as $object) {
            if ($object->hasBeenModified()) {
                if ($allowCreate || $object->hasBeenLoadedFromDb()) {
                    $modified[] = $object;
                }
            } elseif (! $updateOnly && $object->shouldBeRemoved()) {
                $modified[] = $object;
            }
        }

        return $modified;
    }

    /**
     * Transform the given value to an array
     *
     * @param  array|string|null $value
     *
     * @return array
     */
    protected function wantArray($value)
    {
        if (is_array($value)) {
            return $value;
        } elseif ($value === null) {
            return [];
        } else {
            return [$value];
        }
    }

    /**
     * Raise PHP resource limits
     *
     * @return self;
     */
    protected function raiseLimits()
    {
        MemoryLimit::raiseTo('1024M');
        ini_set('max_execution_time', 0);

        return $this;
    }

    /**
     * Initialize run summary measurements
     *
     * @return self;
     */
    protected function startMeasurements()
    {
        $this->run = SyncRun::start($this->rule);
        $this->runStartTime = microtime(true);
        Benchmark::measure('Starting sync');
        return $this;
    }

    /**
     * Fetch the configured properties involved in this sync
     *
     * @return self
     */
    protected function fetchSyncProperties()
    {
        $this->syncProperties = $this->rule->getSyncProperties();
        foreach ($this->syncProperties as $key => $prop) {
            $destinationField = $prop->get('destination_field');
            if ($destinationField === 'vars' && $prop->get('merge_policy') === 'override') {
                $this->replaceVars = true;
            }

            if ($destinationField === 'disabled') {
                $this->hasPropertyDisabled = true;
            }

            if ($prop->get('filter_expression') === null || strlen($prop->get('filter_expression')) === 0) {
                continue;
            }

            $this->columnFilters[$key] = Filter::fromQueryString(
                $prop->get('filter_expression')
            );
        }

        return $this;
    }

    protected function rowMatchesPropertyFilter($row, $key)
    {
        if (!array_key_exists($key, $this->columnFilters)) {
            return true;
        }

        return $this->columnFilters[$key]->matches($row);
    }

    /**
     * Instantiates all related ImportSource objects
     *
     * @return self
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function prepareRelatedImportSources()
    {
        $this->sources = [];
        foreach ($this->syncProperties as $p) {
            $id = $p->get('source_id');
            if (! array_key_exists($id, $this->sources)) {
                $this->sources[$id] = ImportSource::loadWithAutoIncId(
                    (int) $id,
                    $this->db
                );
            }
        }

        return $this;
    }

    /**
     * Prepare the source columns we want to fetch
     *
     * @return self
     */
    protected function prepareSourceColumns()
    {
        // $fieldMap = [];
        $this->sourceColumns = [];

        foreach ($this->syncProperties as $p) {
            $sourceId = $p->get('source_id');
            if (! array_key_exists($sourceId, $this->sourceColumns)) {
                $this->sourceColumns[$sourceId] = [];
            }

            foreach (SyncUtils::extractVariableNames($p->get('source_expression')) as $varname) {
                $this->sourceColumns[$sourceId][$varname] = $varname;
                // -> ? $fieldMap[
            }
        }

        return $this;
    }

    /**
     * Fetch latest imported data rows from all involved import sources
     * @return Sync
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function fetchImportedData()
    {
        Benchmark::measure('Begin loading imported data');
        if ($this->rule->get('object_type') === 'host') {
            $this->serviceOverrideKeyName = $this->db->settings()->override_services_varname;
        }

        $this->imported = [];

        $sourceKeyPattern = $this->rule->getSourceKeyPattern();
        $combinedKey = $this->rule->hasCombinedKey();

        foreach ($this->sources as $source) {
            /** @var ImportSource $source */
            $sourceId = $source->get('id');

            // Provide an alias column for our key. TODO: double-check this!
            $key = $source->key_column;
            $this->sourceColumns[$sourceId][$key] = $key;
            $run = $source->fetchLastRun(true);

            $usedColumns = SyncUtils::getRootVariables($this->sourceColumns[$sourceId]);

            $filterColumns = [];
            foreach ($this->columnFilters as $filter) {
                foreach ($filter->listFilteredColumns() as $column) {
                    $filterColumns[$column] = $column;
                }
            }
            if (($ruleFilter = $this->rule->filter()) !== null) {
                foreach ($ruleFilter->listFilteredColumns() as $column) {
                    $filterColumns[$column] = $column;
                }
            }

            if (! empty($filterColumns)) {
                foreach (SyncUtils::getRootVariables($filterColumns) as $column) {
                    $usedColumns[$column] = $column;
                }
            }
            Benchmark::measure(sprintf('Done pre-processing columns for source %s', $source->source_name));

            $rows = $run->fetchRows($usedColumns);
            Benchmark::measure(sprintf('Fetched source %s', $source->source_name));

            $this->imported[$sourceId] = [];
            foreach ($rows as $row) {
                if ($combinedKey) {
                    $key = SyncUtils::fillVariables($sourceKeyPattern, $row);
                    if ($this->usedLowerCasedKeys) {
                        $key = strtolower($key);
                    }

                    if (array_key_exists($key, $this->imported[$sourceId])) {
                        throw new InvalidArgumentException(sprintf(
                            'Trying to import row "%s" (%s) twice: %s VS %s',
                            $key,
                            $sourceKeyPattern,
                            json_encode($this->imported[$sourceId][$key]),
                            json_encode($row)
                        ));
                    }
                } else {
                    if (! property_exists($row, $key)) {
                        throw new InvalidArgumentException(sprintf(
                            'There is no key column "%s" in this row from "%s": %s',
                            $key,
                            $source->source_name,
                            json_encode($row)
                        ));
                    }
                }

                if (! $this->rule->matches($row)) {
                    continue;
                }

                if ($combinedKey) {
                    $this->imported[$sourceId][$key] = $row;
                } else {
                    if ($this->usedLowerCasedKeys) {
                        $this->imported[$sourceId][strtolower($row->$key)] = $row;
                    } else {
                        $this->imported[$sourceId][$row->$key] = $row;
                    }
                }
            }

            unset($rows);
        }

        Benchmark::measure('Done loading imported data');

        return $this;
    }

    /**
     * TODO: This is rubbish, we need to filter at fetch time
     */
    protected function removeForeignListEntries()
    {
        $listId = null;
        foreach ($this->syncProperties as $prop) {
            if ($prop->get('destination_field') === 'list_id') {
                $listId = (int) $prop->get('source_expression');
            }
        }

        if ($listId === null) {
            throw new InvalidArgumentException(
                'Cannot sync datalist entry without list_id'
            );
        }

        $no = [];
        foreach ($this->objects as $k => $o) {
            if ((int) $o->get('list_id') !== $listId) {
                $no[] = $k;
            }
        }

        foreach ($no as $k) {
            unset($this->objects[$k]);
        }
    }

    /**
     * @return $this
     */
    protected function loadExistingObjects()
    {
        Benchmark::measure('Begin loading existing objects');

        $ruleObjectType = $this->rule->get('object_type');
        $useLowerCaseKeys = $ruleObjectType !== 'datalistEntry';
        // TODO: Make object_type (template, object...) and object_name mandatory?
        if ($this->rule->hasCombinedKey()) {
            $this->objects = [];
            $destinationKeyPattern = $this->rule->getDestinationKeyPattern();
            $table = DbObjectTypeRegistry::tableNameByType($ruleObjectType);
            if ($this->store && BranchSupport::existsForTableName($table)) {
                $objects = $this->store->loadAll($table);
            } else {
                $objects = IcingaObject::loadAllByType($ruleObjectType, $this->db);
            }

            foreach ($objects as $object) {
                if ($object instanceof IcingaService) {
                    if (strstr($destinationKeyPattern, '${host}')
                        && $object->get('host_id') === null
                    ) {
                        continue;
                    } elseif (strstr($destinationKeyPattern, '${service_set}')
                        && $object->get('service_set_id') === null
                    ) {
                        continue;
                    }
                }

                $key = SyncUtils::fillVariables(
                    $destinationKeyPattern,
                    $object
                );
                if ($useLowerCaseKeys) {
                    $key = strtolower($key);
                }

                if (array_key_exists($key, $this->objects)) {
                    throw new InvalidArgumentException(sprintf(
                        'Combined destination key "%s" is not unique, got "%s" twice',
                        $destinationKeyPattern,
                        $key
                    ));
                }

                $this->objects[$key] = $object;
            }
        } else {
            if ($this->store) {
                $objects = $this->store->loadAll(DbObjectTypeRegistry::tableNameByType($ruleObjectType), 'object_name');
            } else {
                $keyColumn = null;
                $query = null;
                // We enforce named index for combined-key templates (Services and Sets) and applied Sets
                if ($ruleObjectType === 'service' || $ruleObjectType === 'serviceSet') {
                    foreach ($this->syncProperties as $prop) {
                        $configuredObjectType = $prop->get('source_expression');
                        if ($prop->get('destination_field') === 'object_type'
                            && (
                                $configuredObjectType === 'template'
                                || ($configuredObjectType === 'apply' && $ruleObjectType === 'serviceSet')
                            )
                        ) {
                            $keyColumn = 'object_name';
                            $table = $ruleObjectType === 'service'
                                ? BranchSupport::TABLE_ICINGA_SERVICE
                                : BranchSupport::TABLE_ICINGA_SERVICE_SET;
                            $query = $this->db->getDbAdapter()->select()
                                ->from($table)->where('object_type = ?', $configuredObjectType);
                        }
                    }
                }
                $objects = IcingaObject::loadAllByType($ruleObjectType, $this->db, $query, $keyColumn);
            }

            if ($useLowerCaseKeys) {
                $this->objects = [];
                foreach ($objects as $key => $object) {
                    $this->objects[strtolower($key)] = $object;
                }
            } else {
                $this->objects = $objects;
            }
        }

        $this->usedLowerCasedKeys = $useLowerCaseKeys;
        // TODO: should be obsoleted by a better "loadFiltered" method
        if ($ruleObjectType === 'datalistEntry') {
            $this->removeForeignListEntries();
        }

        Benchmark::measure('Done loading existing objects');

        return $this;
    }

    /**
     * @return array
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    protected function prepareNewObjects()
    {
        $objects = [];
        $ruleObjectType = $this->rule->get('object_type');

        foreach ($this->sources as $source) {
            $sourceId = $source->id;
            $keyColumn = $source->get('key_column');

            foreach ($this->imported[$sourceId] as $key => $row) {
                // Workaround: $a["10"] = "val"; -> array_keys($a) = [(int) 10]
                $key = (string) $key;
                $originalKey = $row->$keyColumn;
                if ($this->usedLowerCasedKeys) {
                    $key = strtolower($key);
                }
                if (! array_key_exists($key, $objects)) {
                    // Safe default values for object_type and object_name
                    if ($ruleObjectType === 'datalistEntry') {
                        $props = [];
                    } else {
                        $props = [
                            'object_type' => 'object',
                            'object_name' => $originalKey,
                        ];
                    }

                    $objects[$key] = IcingaObject::createByType(
                        $ruleObjectType,
                        $props,
                        $this->db
                    );
                }

                $object = $objects[$key];
                $this->prepareNewObject($row, $object, $key, $sourceId);
            }
        }

        return $objects;
    }

    /**
     * @param $row
     * @param DbObject $object
     * @param $sourceId
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    protected function prepareNewObject($row, DbObject $object, $objectKey, $sourceId)
    {
        foreach ($this->syncProperties as $propertyKey => $p) {
            if ($p->get('source_id') !== $sourceId) {
                continue;
            }

            if (! $this->rowMatchesPropertyFilter($row, $propertyKey)) {
                continue;
            }

            $prop = $p->get('destination_field');
            $val = SyncUtils::fillVariables($p->get('source_expression'), $row);

            if ($object instanceof IcingaObject) {
                if ($prop === 'import') {
                    if ($val !== null) {
                        $object->imports()->add($val);
                    }
                } elseif ($prop === 'groups') {
                    if ($val !== null) {
                        $object->groups()->add($val);
                    }
                } elseif (substr($prop, 0, 5) === 'vars.') {
                    $varName = substr($prop, 5);
                    if (substr($varName, -2) === '[]') {
                        $varName = substr($varName, 0, -2);
                        $current = $this->wantArray($object->vars()->$varName);
                        $object->vars()->$varName = array_merge(
                            $current,
                            $this->wantArray($val)
                        );
                    } else {
                        if ($val === null) {
                            $this->setNull[$objectKey][$prop] = $prop;
                        } else {
                            unset($this->setNull[$objectKey][$prop]);
                            $object->vars()->$varName = $val;
                        }
                    }
                } else {
                    if ($val === null) {
                        $this->setNull[$objectKey][$prop] = $prop;
                    } else {
                        unset($this->setNull[$objectKey][$prop]);
                        $object->set($prop, $val);
                    }
                }
            } else {
                if ($val === null) {
                    $this->setNull[$objectKey][$prop] = $prop;
                } else {
                    unset($this->setNull[$objectKey][$prop]);
                    $object->set($prop, $val);
                }
            }
        }
    }

    /**
     * @return $this
     */
    protected function deferResolvers()
    {
        if (in_array($this->rule->get('object_type'), ['host', 'hostgroup'])) {
            $resolver = $this->getHostGroupMembershipResolver();
            $resolver->defer()->setUseTransactions(false);
        }

        return $this;
    }

    /**
     * @param DbObject $object
     * @return $this
     */
    protected function setResolver($object)
    {
        if (! ($object instanceof IcingaHost || $object instanceof IcingaHostGroup)) {
            return $this;
        }
        if ($resolver = $this->getHostGroupMembershipResolver()) {
            $object->setHostGroupMembershipResolver($resolver);
        }

        return $this;
    }

    /**
     * @return $this
     * @throws \Zend_Db_Adapter_Exception
     */
    protected function notifyResolvers()
    {
        if ($resolver = $this->getHostGroupMembershipResolver()) {
            $resolver->refreshDb(true);
        }

        return $this;
    }

    /**
     * @return bool|HostGroupMembershipResolver
     */
    protected function getHostGroupMembershipResolver()
    {
        if ($this->hostGroupMembershipResolver === null) {
            if (in_array(
                $this->rule->get('object_type'),
                ['host', 'hostgroup']
            )) {
                $this->hostGroupMembershipResolver = new HostGroupMembershipResolver(
                    $this->db
                );
            } else {
                $this->hostGroupMembershipResolver = false;
            }
        }

        return $this->hostGroupMembershipResolver;
    }

    /**
     * Evaluates a SyncRule and returns a list of modified objects
     *
     * TODO: Split this into smaller methods
     *
     * @return DbObject|IcingaObject[] List of modified IcingaObjects
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    protected function prepare()
    {
        if ($this->isPrepared) {
            return $this->objects;
        }

        $this->raiseLimits()
             ->startMeasurements()
             ->prepareCache()
             ->fetchSyncProperties()
             ->prepareRelatedImportSources()
             ->prepareSourceColumns()
             ->loadExistingObjects()
             ->fetchImportedData()
             ->deferResolvers();

        Benchmark::measure('Begin preparing updated objects');
        $newObjects = $this->prepareNewObjects();

        Benchmark::measure('Ready to process objects');
        /** @var DbObject|IcingaObject $object */
        foreach ($newObjects as $key => $object) {
            $this->processObject($key, $object);
        }

        Benchmark::measure('Modified objects are ready, applying purge strategy');
        $noAction = [];
        $purgeAction = $this->rule->get('purge_action');
        foreach ($this->rule->purgeStrategy()->listObjectsToPurge() as $key) {
            $key = strtolower($key);
            if (array_key_exists($key, $newObjects)) {
                // Object has been touched, do not delete
                continue;
            }

            if (array_key_exists($key, $this->objects)) {
                $object = $this->objects[$key];
                if (! $object->hasBeenModified()) {
                    switch ($purgeAction) {
                        case 'delete':
                            $object->markForRemoval();
                            break;
                        case 'disable':
                            $object->set('disabled', 'y');
                            break;
                        default:
                            throw new RuntimeException(
                                "Unsupported purge action: '$purgeAction'"
                            );
                    }
                }
            }
        }

        Benchmark::measure('Done marking objects for purge');

        foreach ($this->objects as $key => $object) {
            if (! $object->hasBeenModified() && ! $object->shouldBeRemoved()) {
                $noAction[] = $key;
            }
        }

        foreach ($noAction as $key) {
            unset($this->objects[$key]);
        }

        $this->isPrepared = true;

        Benchmark::measure('Done preparing objects');

        return $this->objects;
    }

    /**
     * @param $key
     * @param DbObject|IcingaObject $object
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function processObject($key, $object)
    {
        if (array_key_exists($key, $this->objects)) {
            $this->refreshObject($key, $object);
        } else {
            $this->addNewObject($key, $object);
        }
    }

    /**
     * @param $key
     * @param DbObject|IcingaObject $object
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function refreshObject($key, $object)
    {
        $policy = $this->rule->get('update_policy');

        switch ($policy) {
            case 'override':
                if ($object instanceof IcingaHost
                    && !in_array('api_key', $this->rule->getSyncProperties())
                ) {
                    $this->objects[$key]->replaceWith($object, ['api_key']);
                } else {
                    $this->objects[$key]->replaceWith($object);
                }
                break;

            case 'merge':
            case 'update-only':
                // TODO: re-evaluate merge settings. vars.x instead of
                //       just "vars" might suffice.
                $this->objects[$key]->merge($object, $this->replaceVars);
                if (! $this->hasPropertyDisabled && $object->hasProperty('disabled')) {
                    $this->objects[$key]->resetProperty('disabled');
                }
                break;

            default:
                // policy 'ignore', no action
        }

        if ($policy === 'override' || $policy === 'merge') {
            if ($object instanceof IcingaHost) {
                $keyName = $this->serviceOverrideKeyName;
                if (! $object->hasInitializedVars() || ! isset($object->vars()->$key)) {
                    $this->objects[$key]->vars()->restoreStoredVar($keyName);
                }
            }
        }

        if (isset($this->setNull[$key])) {
            foreach ($this->setNull[$key] as $property) {
                $this->objects[$key]->set($property, null);
            }
        }
    }

    /**
     * @param $key
     * @param DbObject|IcingaObject $object
     */
    protected function addNewObject($key, $object)
    {
        $this->objects[$key] = $object;
    }

    /**
     * Runs a SyncRule and applies all resulting changes
     * @return int
     * @throws Exception
     * @throws IcingaException
     */
    public function apply()
    {
        Benchmark::measure('Begin applying objects');

        $objects = $this->prepare();
        $db = $this->db;
        $dba = $db->getDbAdapter();
        if (! $this->store) { // store has it's own transaction
            $dba->beginTransaction();
        }

        $object = null;
        $updateOnly = $this->rule->get('update_policy') === 'update-only';
        $allowCreate = ! $updateOnly;

        try {
            $formerActivityChecksum = hex2bin(
                $db->getLastActivityChecksum()
            );
            $created = 0;
            $modified = 0;
            $deleted = 0;
            // TODO: Count also failed ones, once we allow such
            // $failed = 0;
            foreach ($objects as $object) {
                $this->setResolver($object);
                if (! $updateOnly && $object->shouldBeRemoved()) {
                    if ($this->store) {
                        $this->store->delete($object);
                    } else {
                        $object->delete();
                    }
                    $deleted++;
                    continue;
                }

                if ($object->hasBeenModified()) {
                    $existing = $object->hasBeenLoadedFromDb();
                    if ($existing) {
                        if ($this->store) {
                            $this->store->store($object);
                        } else {
                            $object->store($db);
                        }
                        $modified++;
                    } elseif ($allowCreate) {
                        if ($this->store) {
                            $this->store->store($object);
                        } else {
                            $object->store($db);
                        }
                        $created++;
                    }
                }
            }

            $runProperties = [
                'objects_created'  => $created,
                'objects_deleted'  => $deleted,
                'objects_modified' => $modified,
            ];

            if ($created + $deleted + $modified > 0) {
                // TODO: What if this has been the very first activity?
                $runProperties['last_former_activity'] = $db->quoteBinary($formerActivityChecksum);
                $runProperties['last_related_activity'] = $db->quoteBinary(hex2bin(
                    $db->getLastActivityChecksum()
                ));
            }

            $this->run->setProperties($runProperties);
            if (!$this->store || !$this->store->getBranch()->isBranch()) {
                $this->run->store();
            }
            $this->notifyResolvers();
            if (! $this->store) {
                $dba->commit();
            }

            // Store duration after commit, as the commit might take some time
            $this->run->set('duration_ms', (int) round(
                (microtime(true) - $this->runStartTime) * 1000
            ));
            if (!$this->store || !$this->store->getBranch()->isBranch()) {
                $this->run->store();
            }

            Benchmark::measure('Done applying objects');
        } catch (Exception $e) {
            if (! $this->store) {
                $dba->rollBack();
            }

            if ($object instanceof IcingaObject) {
                throw new IcingaException(
                    'Exception while syncing %s %s: %s',
                    get_class($object),
                    $object->getObjectName(),
                    $e->getMessage(),
                    $e
                );
            } else {
                throw $e;
            }
        }

        return $this->run->get('id');
    }

    protected function prepareCache()
    {
        if ($this->store) {
            return $this;
        }
        PrefetchCache::initialize($this->db);
        IcingaTemplateRepository::clear();

        $ruleObjectType = $this->rule->get('object_type');

        $dummy = IcingaObject::createByType($ruleObjectType);
        if ($dummy instanceof IcingaObject) {
            IcingaObject::prefetchAllRelationsByType($ruleObjectType, $this->db);
        }

        return $this;
    }
}
