<?php

namespace Icinga\Module\Director\Import;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\IcingaService;
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
     * @var array
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

    protected $hasCombinedKey;

    protected $sourceKeyPattern;

    protected $destinationKeyPattern;

    protected $syncProperties;

    protected $run;

    protected $runStartTime;

    protected $columnFilters = array();

    /**
     * Constructor. No direct initialization allowed right now. Please use one
     * of the available static factory methods
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
            }
        }

        return $modified;
    }

    /**
     * Extract variable names in the form ${var_name} from a given string
     *
     * @param  string $string
     *
     * @return array  List of variable names (without ${})
     */
    protected function extractVariableNames($string)
    {
        if (preg_match_all('/\${([A-Za-z0-9\._-]+)}/', $string, $m, PREG_PATTERN_ORDER)) {
            return $m[1];
        } else {
            return array();
        }
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
     * Recursively extract a value from a nested structure
     *
     * For a $val looking like
     *
     * { 'vars' => { 'disk' => { 'sda' => { 'size' => '256G' } } } }
     *
     * and a key vars.disk.sda given as [ 'vars', 'disk', 'sda' ] this would
     * return { size => '255GB' }
     *
     * @param  string $val  The value to extract data from
     * @param  object $keys A list of nested keys pointing to desired data
     *
     * @return mixed
     */
    protected function getDeepValue($val, $keys)
    {
        $key = array_shift($keys);
        if (! property_exists($val, $key)) {
            return null;
        }

        if (empty($keys)) {
            return $val->$key;
        }

        return $this->getDeepValue($val->$key, $keys);
    }

    /**
     * Return a specific value from a given row object
     *
     * Supports also keys pointing to nested structures like vars.disk.sda
     *
     * @param  object $row    stdClass object providing property values
     * @param  string $string Variable/property name
     *
     * @return mixed
     */
    public function getSpecificValue($row, $var)
    {
        if (strpos($var, '.') === false) {
            if ($row instanceof IcingaObject) {
                return $row->$var;
            }
            if (! property_exists($row, $var)) {
                return null;
            }

            return $row->$var;
        } else {
            $parts = explode('.', $var);
            $main = array_shift($parts);
            if (! is_object($row->$main)) {
                throw new IcingaException('Data is not nested, cannot access %s: %s', $var, var_export($row, 1));
            }

            return $this->getDeepValue($row->$main, $parts);
        }
    }

    /**
     * Fill variables in the given string pattern
     *
     * This replaces all occurances of ${var_name} with the corresponding
     * property $row->var_name of the given row object. Missing variables are
     * replaced by an empty string. This works also fine in case there are
     * multiple variables to be found in your string.
     *
     * @param  string $string String with opional variables/placeholders
     * @param  object $row    stdClass object providing property values
     *
     * @return string
     */
    protected function fillVariables($string, $row)
    {
        if (preg_match('/^\${([A-Za-z0-9\._-]+)}$/', $string, $m)) {
            return $this->getSpecificValue($row, $m[1]);
        }

        // PHP 5.3 :(
        $self = $this;
        $func = function ($match) use ($self, $row) {
            return $self->getSpecificValue($row, $match[1]);
        };

        return preg_replace_callback('/\${([A-Za-z0-9\._-]+)}/', $func, $string);
    }

    /**
     * Raise PHP resource limits
     *
     * TODO: do this in a failsafe way, and only if necessary
     *
     * @return self;
     */
    protected function raiseLimits()
    {
        ini_set('memory_limit', '768M');
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
        return $this;
    }

    /**
     * Fetch the configured properties involved in this sync
     *
     * @return self
     */
    protected function fetchSyncProperties()
    {
        $this->syncProperties = $this->rule->fetchSyncProperties();
        foreach ($this->syncProperties as $key => $prop) {
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

            foreach ($this->extractVariableNames($p->source_expression) as $varname) {
                $this->sourceColumns[$sourceId][$varname] = $varname;
                // -> ? $fieldMap[
            }
        }

        return $this;
    }

    /**
     * Whether we have a combined key (e.g. services on hosts)
     *
     * @return bool
     */
    protected function hasCombinedKey()
    {
        if ($this->hasCombinedKey === null) {

            $this->hasCombinedKey = false;

            if ($this->rule->object_type === 'service') {
                $hasHost = false;
                $hasObjectName = false;

                foreach ($this->syncProperties as $key => $property) {
                    if ($property->destination_field === 'host') {
                        $hasHost = $property->source_expression;
                    }
                    if ($property->destination_field === 'object_name') {
                        $hasObjectName = $property->source_expression;
                    }
                }

                if ($hasHost !== false && $hasObjectName !== false) {
                    $this->hasCombinedKey = true;
                    $this->sourceKeyPattern = sprintf(
                        '%s!%s',
                        $hasHost,
                        $hasObjectName
                    );

                    $this->destinationKeyPattern = '${host}!${object_name}';
                }
            }
        }

        return $this->hasCombinedKey;
    }

    /**
     * Fetch latest imported data rows from all involved import sources
     *
     * @return self
     */
    protected function fetchImportedData()
    {
        $this->imported = array();

        foreach ($this->sources as $source) {
            $sourceId = $source->id;

            // Provide an alias column for our key. TODO: double-check this!
            $key = $source->key_column;
            $this->sourceColumns[$sourceId][$key] = $key;
            $rows = $this->db->fetchLatestImportedRows(
                $sourceId,
                $this->sourceColumns[$sourceId]
            );

            $this->imported[$sourceId] = array();
            foreach ($rows as $row) {
                if ($this->hasCombinedKey()) {
                    $key = $this->fillVariables($this->sourceKeyPattern, $row);
                    if (array_key_exists($key, $this->imported[$sourceId])) {
                        throw new IcingaException(
                            'Trying to import row "%s" (%s) twice: %s VS %s',
                            $key,
                            $this->sourceKeyPattern,
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

                if ($this->hasCombinedKey()) {
                    $this->imported[$sourceId][$key] = $row;
                } else {
                    $this->imported[$sourceId][$row->$key] = $row;
                }
            }
        }

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
            if ($o->list_id !== $listId) {
                $no[] = $k;
            }
        }

        foreach ($no as $k) {
            unset($this->objects[$k]);
        }
    }

    protected function loadExistingObjects()
    {
        // TODO: Make object_type (template, object...) and object_name mandatory?
        if ($this->hasCombinedKey()) {

            $this->objects = array();

            foreach (IcingaObject::loadAllByType(
                $this->rule->object_type,
                $this->db
            ) as $object) {

                if ($object instanceof IcingaService) {
                    if (! $object->host_id) {
                        continue;
                    }
                }

                $key = $this->fillVariables(
                    $this->destinationKeyPattern,
                    $object
                );

                if (array_key_exists($key, $this->objects)) {
                    throw new IcingaException(
                        'Combined destination key "%s" is not unique, got "%s" twice',
                        $this->destinationKeyPattern,
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
            $this->removeForeignListEntries($this->objects);
        }

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

                    $val = $this->fillVariables($p->source_expression, $row);

                    if (substr($prop, 0, 5) === 'vars.') {
                        $varName = substr($prop, 5);
                        if (substr($varName, -2) === '[]') {
                            $varName = substr($varName, 0, -2);
                            $val = $this->wantArray($val);
                        }
                        $newVars[$varName] = $val;
                    } else {
                        if ($prop === 'import') {
                            $imports[] = $val;
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

    /**
     * Evaluates a SyncRule and returns a list of modified objects
     *
     * TODO: This needs to be splitted into smaller methods
     *
     * @return array          List of modified IcingaObjects
     */
    protected function prepare()
    {
        if ($this->isPrepared) {
            return $this->objects;
        }

        $this->raiseLimits()
             ->startMeasurements()
             ->fetchSyncProperties()
             ->prepareRelatedImportSources()
             ->prepareSourceColumns()
             ->loadExistingObjects()
             ->fetchImportedData();

        // TODO: directly work on existing objects, remember imported keys, then purge
        $newObjects = $this->prepareNewObjects();

        foreach ($newObjects as $key => $object) {
            if (array_key_exists($key, $this->objects)) {
                switch ($this->rule->update_policy) {
                    case 'override':
                        $this->objects[$key]->replaceWith($object);
                        break;

                    case 'merge':
                        // TODO: re-evaluate merge settings. vars.x instead of
                        //       just "vars" might suffice.
                        $this->objects[$key]->merge($object);
                        break;

                    default:
                        // policy 'ignore', no action
                }
            } else {
                $this->objects[$key] = $object;
            }
        }

        $noAction = array();

        foreach ($this->objects as $key => $object) {

            if (array_key_exists($key, $newObjects)) {
                // Stats?

            } elseif ($object->hasBeenLoadedFromDb() && $this->rule->purge_existing === 'y') {
                $object->markForRemoval();

                // TODO: this is for stats, preview, summary:
                // $this->remove[] = $object;
            } else {
                $noAction[] = $key;
            }
        }

        foreach ($noAction as $key) {
            unset($this->objects[$key]);
        }

        $this->isPrepared = true;

        return $this->objects;
    }

    /**
     * Runs a SyncRule and applies all resulting changes
     *
     * TODO: Should return the id of the related sync_history table entry.
     *       Such a table does not yet exist, so 42 is the answer right now.
     *
     * @return int
     */
    public function apply()
    {
        $objects = $this->prepare();
        $db = $this->db;
        $dba = $db->getDbAdapter();
        $dba->beginTransaction();
        $formerActivityChecksum = Util::hex2binary(
            $db->getLastActivityChecksum()
        );
        $created = 0;
        $modified = 0;
        $deleted = 0;
        foreach ($objects as $object) {
            if ($object instanceof IcingaObject && $object->isTemplate()) {
                // TODO: allow to sync templates
                if ($object->hasBeenModified()) {
                    throw new IcingaException(
                        'Sync is not allowed to modify template "%s"',
                        $object->$objectKey
                    );
                }
                continue;
            }

            if ($object instanceof IcingaObject && $object->shouldBeRemoved()) {
                $object->delete($db);
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

        $dba->commit();

        // Store duration after commit, as the commit might take some time
        $this->run->set('duration_ms', (int) round(
            (microtime(true) - $this->runStartTime) * 1000
        ))->store();


        return $this->run->id;
    }
}
