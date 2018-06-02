<?php

namespace Icinga\Module\Director\Import;

use Exception;
use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Application\MemoryLimit;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Db;
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
use Icinga\Module\Director\Resolver\TemplateTree;
use Icinga\Module\Director\Util;
use Icinga\Exception\IcingaException;

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

    /** @var bool Whether we already prepared your sync */
    protected $isPrepared = false;

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

    /**
     * @param SyncRule $rule
     */
    public function __construct(SyncRule $rule)
    {
        $this->rule = $rule;
        $this->db = $rule->getConnection();
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
     * @throws Exception
     */
    public function getExpectedModifications()
    {
        $modified = [];
        $objects = $this->prepare();
        foreach ($objects as $object) {
            if ($object->hasBeenModified()) {
                $modified[] = $object;
            } elseif ($object->shouldBeRemoved()) {
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
            if ($prop->destination_field === 'vars' && $prop->merge_policy === 'override') {
                $this->replaceVars = true;
            }

            if ($prop->destination_field === 'disabled') {
                $this->hasPropertyDisabled = true;
            }

            if (! strlen($prop->filter_expression)) {
                continue;
            }

            $this->columnFilters[$key] = Filter::fromQueryString(
                $prop->filter_expression
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
     * @throws IcingaException
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function prepareRelatedImportSources()
    {
        $this->sources = [];
        foreach ($this->syncProperties as $p) {
            $id = $p->source_id;
            if (! array_key_exists($id, $this->sources)) {
                $this->sources[$id] = ImportSource::load($id, $this->db);
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
            $sourceId = $p->source_id;
            if (! array_key_exists($sourceId, $this->sourceColumns)) {
                $this->sourceColumns[$sourceId] = [];
            }

            foreach (SyncUtils::extractVariableNames($p->source_expression) as $varname) {
                $this->sourceColumns[$sourceId][$varname] = $varname;
                // -> ? $fieldMap[
            }
        }

        return $this;
    }

    /**
     * Fetch latest imported data rows from all involved import sources
     * @return Sync
     * @throws IcingaException
     */
    protected function fetchImportedData()
    {
        Benchmark::measure('Begin loading imported data');
        if ($this->rule->object_type === 'host') {
            $this->serviceOverrideKeyName = $this->db->settings()->override_services_varname;
        }

        $this->imported = [];

        $sourceKeyPattern = $this->rule->getSourceKeyPattern();
        $combinedKey = $this->rule->hasCombinedKey();

        foreach ($this->sources as $source) {
            /** @var ImportSource $source */
            $sourceId = $source->id;

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

                    if (array_key_exists($key, $this->imported[$sourceId])) {
                        throw new IcingaException(
                            'Trying to import row "%s" (%s) twice: %s VS %s',
                            $key,
                            $sourceKeyPattern,
                            json_encode($this->imported[$sourceId][$key]),
                            json_encode($row)
                        );
                    }
                } else {
                    if (! property_exists($row, $key)) {
                        throw new IcingaException(
                            'There is no key column "%s" in this row from "%s": %s',
                            $key,
                            $source->source_name,
                            json_encode($row)
                        );
                    }
                }

                if (! $this->rule->matches($row)) {
                    continue;
                }

                if ($combinedKey) {
                    $this->imported[$sourceId][$key] = $row;
                } else {
                    $this->imported[$sourceId][$row->$key] = $row;
                }
            }

            unset($rows);
        }

        Benchmark::measure('Done loading imported data');

        return $this;
    }

    /**
     * TODO: This is rubbish, we need to filter at fetch time
     *
     * @throws IcingaException
     */
    protected function removeForeignListEntries()
    {
        $listId = null;
        foreach ($this->syncProperties as $prop) {
            if ($prop->destination_field === 'list_id') {
                $listId = (int) $prop->source_expression;
            }
        }

        if ($listId === null) {
            throw new IcingaException(
                'Cannot sync datalist entry without list_ist'
            );
        }

        $no = [];
        foreach ($this->objects as $k => $o) {
            if ((int) $o->list_id !== (int) $listId) {
                $no[] = $k;
            }
        }

        foreach ($no as $k) {
            unset($this->objects[$k]);
        }
    }

    /**
     * @return $this
     * @throws IcingaException
     */
    protected function loadExistingObjects()
    {
        Benchmark::measure('Begin loading existing objects');

        // TODO: Make object_type (template, object...) and object_name mandatory?
        if ($this->rule->hasCombinedKey()) {
            $this->objects = [];
            $destinationKeyPattern = $this->rule->getDestinationKeyPattern();

            foreach (IcingaObject::loadAllByType(
                $this->rule->object_type,
                $this->db
            ) as $object) {
                if ($object instanceof IcingaService) {
                    if (strstr($destinationKeyPattern, '${host}') && $object->host_id === null) {
                        continue;
                    } elseif (strstr($destinationKeyPattern, '${service_set}') && $object->service_set_id === null) {
                        continue;
                    }
                }

                $key = SyncUtils::fillVariables(
                    $destinationKeyPattern,
                    $object
                );

                if (array_key_exists($key, $this->objects)) {
                    throw new IcingaException(
                        'Combined destination key "%s" is not unique, got "%s" twice',
                        $destinationKeyPattern,
                        $key
                    );
                }

                $this->objects[$key] = $object;
            }
        } else {
            $this->objects = IcingaObject::loadAllByType(
                $this->rule->object_type,
                $this->db
            );
        }

        // TODO: should be obsoleted by a better "loadFiltered" method
        if ($this->rule->object_type === 'datalistEntry') {
            $this->removeForeignListEntries();
        }

        Benchmark::measure('Done loading existing objects');

        return $this;
    }

    /**
     * @return array
     * @throws IcingaException
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function prepareNewObjects()
    {
        $objects = [];

        foreach ($this->sources as $source) {
            $sourceId = $source->id;

            foreach ($this->imported[$sourceId] as $key => $row) {
                if (! array_key_exists($key, $objects)) {
                    // Safe default values for object_type and object_name
                    if ($this->rule->object_type === 'datalistEntry') {
                        $props = [];
                    } else {
                        $props = [
                            'object_type' => 'object',
                            'object_name' => $key
                        ];
                    }

                    $objects[$key] = IcingaObject::createByType(
                        $this->rule->object_type,
                        $props,
                        $this->db
                    );
                }

                $object = $objects[$key];
                $this->prepareNewObject($row, $object, $sourceId);
            }
        }

        return $objects;
    }

    /**
     * @param $row
     * @param DbObject $object
     * @param $sourceId
     * @throws IcingaException
     * @throws \Icinga\Exception\NotFoundError
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function prepareNewObject($row, DbObject $object, $sourceId)
    {
        foreach ($this->syncProperties as $propertyKey => $p) {
            if ($p->source_id !== $sourceId) {
                continue;
            }

            if (! $this->rowMatchesPropertyFilter($row, $propertyKey)) {
                continue;
            }

            $prop = $p->destination_field;

            $val = SyncUtils::fillVariables($p->source_expression, $row);

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
                        $object->vars()->$varName = $val;
                    }
                } else {
                    if ($val !== null) {
                        $object->set($prop, $val);
                    }
                }
            } else {
                if ($val !== null) {
                    $object->set($prop, $val);
                }
            }
        }
    }

    /**
     * @return $this
     * @throws IcingaException
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
     * @throws IcingaException
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
     * @throws IcingaException
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
     * @throws IcingaException
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
     * @throws Exception
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
        foreach ($this->rule->purgeStrategy()->listObjectsToPurge() as $key) {
            if (array_key_exists($key, $newObjects)) {
                // Object has been touched, do not delete
                continue;
            }

            if (array_key_exists($key, $this->objects)) {
                $object = $this->objects[$key];
                if (! $object->hasBeenModified()) {
                    $object->markForRemoval();
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
     * @throws IcingaException
     * @throws \Icinga\Exception\ProgrammingError
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
     * @throws IcingaException
     * @throws \Icinga\Exception\ProgrammingError
     */
    protected function refreshObject($key, $object)
    {
        $policy = $this->rule->get('update_policy');

        switch ($policy) {
            case 'override':
                $this->objects[$key]->replaceWith($object);
                break;

            case 'merge':
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
        $dba->beginTransaction();

        $object = null;

        try {
            $formerActivityChecksum = Util::hex2binary(
                $db->getLastActivityChecksum()
            );
            $created = 0;
            $modified = 0;
            $deleted = 0;
            $failed = 0;
            foreach ($objects as $object) {
                $this->setResolver($object);
                if ($object->shouldBeRemoved()) {
                    $object->delete();
                    $deleted++;
                    continue;
                }

                if ($object->hasBeenModified()) {
                    $existing = $object->hasBeenLoadedFromDb();
                    $object->store($db);

                    if ($existing) {
                        $modified++;
                    } else {
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
                $runProperties['last_related_activity'] = $db->quoteBinary(Util::hex2binary(
                    $db->getLastActivityChecksum()
                ));
            }

            $this->run->setProperties($runProperties)->store();
            $this->notifyResolvers();
            $dba->commit();

            // Store duration after commit, as the commit might take some time
            $this->run->set('duration_ms', (int) round(
                (microtime(true) - $this->runStartTime) * 1000
            ))->store();

            Benchmark::measure('Done applying objects');
        } catch (Exception $e) {
            $dba->rollBack();

            if ($object !== null && $object instanceof IcingaObject) {
                throw new IcingaException(
                    'Exception while syncing %s %s: %s',
                    get_class($object),
                    $object->get('object_name'),
                    $e->getMessage(),
                    $e
                );
            } else {
                throw $e;
            }
        }

        return $this->run->id;
    }

    protected function prepareCache()
    {
        PrefetchCache::initialize($this->db);

        $dummy = IcingaObject::createByType($this->rule->object_type);
        if ($dummy instanceof IcingaObject) {
            IcingaObject::prefetchAllRelationsByType($this->rule->object_type, $this->db);
        }

        TemplateTree::setSyncMode();

        return $this;
    }
}
