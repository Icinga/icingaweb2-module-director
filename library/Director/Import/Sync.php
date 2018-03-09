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
use Icinga\Module\Director\Util;
use Icinga\Exception\IcingaException;

class Sync
{
    /**
     * @var SyncRule
     */
    protected $rule;

    /**
     * @var Db
     */
    protected $db;

    /**
     * Related ImportSource objects
     *
     * @var array
     */
    protected $sources;

    /**
     * Source columns we want to fetch from our sources
     *
     * @var array
     */
    protected $sourceColumns;

    /**
     * Imported data
     */
    protected $imported;

    /**
     * Objects to work with
     *
     * @var IcingaObject[]
     */
    protected $objects;

    /**
     * Whether we already prepared your sync
     *
     * @var bool
     */
    protected $isPrepared = false;

    protected $modify = array();

    protected $remove = array();

    protected $create = array();

    protected $errors = array();

    /** @var SyncProperty[] */
    protected $syncProperties;

    protected $replaceVars = false;

    /**
     * @var SyncRun
     */
    protected $run;

    protected $runStartTime;

    /** @var Filter[] */
    protected $columnFilters = array();

    /** @var HostGroupMembershipResolver|bool */
    protected $hostGroupMembershipResolver;

    /**
     * Constructor. No direct initialization allowed right now. Please use one
     * of the available static factory methods
     *
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
     */
    public function hasModifications()
    {
        return count($this->getExpectedModifications()) > 0;
    }

    /**
     * Retrieve modifications a given SyncRule would apply
     *
     * @return array  Array of IcingaObject elements
     */
    public function getExpectedModifications()
    {
        $modified = array();
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
            return array();
        } else {
            return array($value);
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
     */
    protected function prepareRelatedImportSources()
    {
        $this->sources = array();
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
        // $fieldMap = array();
        $this->sourceColumns = array();

        foreach ($this->syncProperties as $p) {
            $sourceId = $p->source_id;
            if (! array_key_exists($sourceId, $this->sourceColumns)) {
                $this->sourceColumns[$sourceId] = array();
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

        $this->imported = array();

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

            $filterColumns = array();
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

            $this->imported[$sourceId] = array();
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

    // TODO: This is rubbish, we need to filter at fetch time
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

        $no = array();
        foreach ($this->objects as $k => $o) {
            if ((int) $o->list_id !== (int) $listId) {
                $no[] = $k;
            }
        }

        foreach ($no as $k) {
            unset($this->objects[$k]);
        }
    }

    protected function loadExistingObjects()
    {
        Benchmark::measure('Begin loading existing objects');

        // TODO: Make object_type (template, object...) and object_name mandatory?
        if ($this->rule->hasCombinedKey()) {
            $this->objects = array();
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

    protected function prepareNewObjects()
    {
        $newObjects = array();

        foreach ($this->sources as $source) {
            $sourceId = $source->id;

            foreach ($this->imported[$sourceId] as $key => $row) {
                $newProps = array();

                $newVars = array();
                $imports = array();

                foreach ($this->syncProperties as $propertyKey => $p) {
                    if ($p->source_id !== $sourceId) {
                        continue;
                    }

                    if (! $this->rowMatchesPropertyFilter($row, $propertyKey)) {
                        continue;
                    }

                    $prop = $p->destination_field;

                    $val = SyncUtils::fillVariables($p->source_expression, $row);

                    if (substr($prop, 0, 5) === 'vars.') {
                        $varName = substr($prop, 5);
                        if (substr($varName, -2) === '[]') {
                            $varName = substr($varName, 0, -2);
                            $val = $this->wantArray($val);
                        }
                        $newVars[$varName] = $val;
                    } else {
                        if ($prop === 'import') {
                            if (is_array($val)) {
                                $imports = array_merge($imports, $val);
                            } elseif (!is_null($val)) {
                                $imports[] = $val;
                            }
                        } else {
                            $newProps[$prop] = $val;
                        }
                    }
                }
                if (! array_key_exists($key, $newObjects)) {
                    $newObjects[$key] = IcingaObject::createByType(
                        $this->rule->object_type,
                        array(),
                        $this->db
                    );
                }

                $object = $newObjects[$key];

                // Safe default values for object_type and object_name
                if ($this->rule->object_type !== 'datalistEntry') {
                    if (! array_key_exists('object_type', $newProps)
                        || $newProps['object_type'] === null
                    ) {
                        $newProps['object_type'] = 'object';
                    }

                    if (! array_key_exists('object_name', $newProps)
                        || $newProps['object_name'] === null
                    ) {
                        $newProps['object_name'] = $key;
                    }
                }

                foreach ($newProps as $prop => $value) {
                    // TODO: data type?
                    $object->set($prop, $value);
                }

                foreach ($newVars as $prop => $var) {
                    $object->vars()->$prop = $var;
                }

                if (! empty($imports)) {
                    // TODO: merge imports!!!
                    $object->imports()->set($imports);
                }
            }
        }

        return $newObjects;
    }

    protected function deferResolvers()
    {
        if (in_array($this->rule->get('object_type'), array('host', 'hostgroup'))) {
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
                array('host', 'hostgroup')
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
     * TODO: This needs to be splitted into smaller methods
     *
     * @return DbObject[]          List of modified IcingaObjects
     */
    protected function prepare()
    {
        if ($this->isPrepared) {
            return $this->objects;
        }

        PrefetchCache::initialize($this->db);

        $this->raiseLimits()
             ->startMeasurements()
             ->fetchSyncProperties()
             ->prepareRelatedImportSources()
             ->prepareSourceColumns()
             ->loadExistingObjects()
             ->fetchImportedData()
             ->deferResolvers();

        // TODO: directly work on existing objects, remember imported keys, then purge
        $newObjects = $this->prepareNewObjects();

        $hasDisabled = false;
        foreach ($this->syncProperties as $property) {
            if ($property->get('destination_field') === 'disabled') {
                $hasDisabled = true;
            }
        }

        Benchmark::measure('Begin preparing updated objects');

        /** @var DbObject|IcingaObject $object */
        foreach ($newObjects as $key => $object) {
            if (array_key_exists($key, $this->objects)) {
                switch ($this->rule->get('update_policy')) {
                    case 'override':
                        $this->objects[$key]->replaceWith($object);
                        break;

                    case 'merge':
                        // TODO: re-evaluate merge settings. vars.x instead of
                        //       just "vars" might suffice.
                        $this->objects[$key]->merge($object, $this->replaceVars);
                        if (! $hasDisabled && $object->hasProperty('disabled')) {
                            $this->objects[$key]->resetProperty('disabled');
                        }
                        break;

                    default:
                        // policy 'ignore', no action
                }
            } else {
                $this->objects[$key] = $object;
            }
        }

        Benchmark::measure('Done preparing updated objects');

        $noAction = array();
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
            foreach ($objects as $object) {
                $this->setResolver($object);
                if ($object->shouldBeRemoved()) {
                    $object->delete();
                    $deleted++;
                    continue;
                }

                if ($object->hasBeenModified()) {
                    if ($object->hasBeenLoadedFromDb()) {
                        $modified++;
                    } else {
                        $created++;
                    }
                    $object->store($db);
                }
            }

            $runProperties = array(
                'objects_created'  => $created,
                'objects_deleted'  => $deleted,
                'objects_modified' => $modified,
            );

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
}
