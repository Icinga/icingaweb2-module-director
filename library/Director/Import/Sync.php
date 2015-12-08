<?php

namespace Icinga\Module\Director\Import;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Exception\IcingaException;

class Sync
{
    /**
     * Constructor. No direct initialization allowed right now. Please use one
     * of the available static factory methods
     */
    protected function __construct()
    {
    }

    /**
     * Run the given sync rule
     */
    public static function run(SyncRule $rule)
    {
        $sync = new static;
        return $sync->runWithRule($rule);
    }

    /**
     * Whether the given sync rule would apply modifications
     *
     * @param  SyncRule $rule SyncRule object
     *
     * @return boolean
     */
    public static function hasModifications(SyncRule $rule)
    {
        return count(self::getExpectedModifications($rule)) > 0;
    }

    /**
     * Retrieve modifications a given SyncRule would apply
     *
     * @param  SyncRule $rule SyncRule object
     *
     * @return array  Array of IcingaObject elements
     */
    public static function getExpectedModifications(SyncRule $rule)
    {
        $modified = array();
        $sync = new static;
        $objects = $sync->prepareSyncForRule($rule);
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
    protected function getSpecificValue($row, $var)
    {
        if (strpos($var, '.') === false) {
            if (! property_exists($row, $var)) {
                return null;
            }

            return $row->$var;
        } else {
            $parts = explode('.', $var);
            $main = array_shift($parts);
            if (! is_object($row->$main)) {
                die('Data is not nested, cannot access ...');
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

        $func = function ($match) use ($row) {
            return $this->getSpecificValue($row, $match[1]);
        };

        return preg_replace_callback('/\${([A-Za-z0-9\._-]+)}/', $func, $string);
    }

    protected function perpareImportSources($properties, $db)
    {
        $sources = array();
        foreach ($properties as $p) {
            $sourceId = $p->source_id;
            if (! array_key_exists($sourceId, $sources)) {
                $sources[$sourceId] = ImportSource::load($sourceId, $db);
            }
        }

        return $sources;
    }

    protected function prepareSourceColumns($properties)
    {
        // $fieldMap = array();
        $columns = array();

        foreach ($properties as $p) {
            $sourceId = $p->source_id;
            if (! array_key_exists($sourceId, $sources)) {
                $columns[$sourceId] = array();
            }

            foreach ($this->extractVariableNames($p->source_expression) as $varname) {
                $columns[$sourceId][$varname] = $varname;
                // -> ? $fieldMap[
            }
        }

        return $columns;
    }

    protected function fetchImportedData($sources, $properties, $db)
    {
        $imported = array();

        $sourceColumns = $this->prepareSourceColumns($properties);

        foreach ($sources as $source) {
            $sourceId = $source->id;
            $key = $source->key_column;
            $sourceColumns[$sourceId][$key] = $key;
            $rows = $db->fetchLatestImportedRows($sourceId, $sourceColumns[$sourceId]);

            $imported[$sourceId] = array();
            foreach ($rows as $row) {
                if (! property_exists($row, $key)) {
                    throw new IcingaException(
                        'There is no key column "%s" in this row from "%s": %s',
                        $key,
                        $source->source_name,
                        json_encode($row)
                    );
                }
                if (! $rule->matches($row)) {
                    continue;
                }
                $imported[$sourceId][$row->$key] = $row;
            }
        }

        return $imported;
    }

    /**
     * Evaluates a SyncRule and returns a list of modified objects
     *
     * TODO: This needs to be splitted into smaller methods
     *
     * @param  SyncRule $rule The synchronization rule that should be used
     *
     * @return array          List of modified IcingaObjects
     */
    protected function prepareSyncForRule(SyncRule $rule)
    {
        $db = $rule->getConnection();
        $properties = $rule->fetchSyncProperties();
        $sources = $this->perpareImportSources($properties, $db);
        $imported = $this->fetchImportedData($sources, $properties, $db);

        // TODO: Filter auf object, nicht template
        $objects = IcingaObject::loadAllByType($rule->object_type, $db);

        if ($rule->object_type === 'datalistEntry') {
            $no = array();
            foreach ($objects as $o) {
             //   if ($o->list_id !== $source->
            }
        }
        $dummy = IcingaObject::createByType($rule->object_type, array());
        $objectKey = $rule->object_type === 'datalistEntry' ? 'entry_name' : 'object_name';

        foreach ($sources as $source) {
            $sourceId = $source->id;

            foreach ($imported[$sourceId] as $key => $row) {
                $newProps = array();

                $newVars = array();
                $imports = array();

                foreach ($properties as $p) {
                    if ($p->source_id !== $sourceId) continue;

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

                if (array_key_exists($key, $objects)) {

                    switch ($rule->update_policy) {
                        case 'override':
// TODO: Only override if it doesn't equal
                            $objects[$key] = IcingaObject::createByType(
                                $rule->object_type,
                                $newProps,
                                $db
                            );

                            foreach ($newVars as $prop => $var) {
                                $objects[$key]->vars()->$prop = $var;
                            }
                            if (! empty($imports)) {
                                $objects[$key]->imports()->set($imports);
                            }
                            break;

                        case 'merge':
                            $object = $objects[$key];
                            foreach ($newProps as $prop => $value) {
                                // TODO: data type? 
                                $object->set($prop, $value);

                            }

                            foreach ($newVars as $prop => $var) {
                                // TODO: property merge policy
                                $object->vars()->$prop = $var;
                            }

                            if (! empty($imports)) {
                                // TODO: merge imports ?!
                                $objects[$key]->imports()->set($imports);
                            }
                            break;

                        default:
                            // policy 'ignore', no action
                    }
                } else {
                    // New object
                    if ($rule->object_type !== 'datalistEntry') {
                        $newProps['object_type'] = 'object';
                        $newProps['object_name'] = $key;
                    }

                    $objects[$key] = IcingaObject::createByType($rule->object_type, $newProps, $db);
                    foreach ($newVars as $prop => $var) {
                        $objects[$key]->vars()->$prop = $var;
                    }

                    if (! empty($imports)) {
                        $objects[$key]->imports()->set($imports);
                    }
                }
            }
        }

        $ignore = array();

        foreach ($objects as $key => $object) {

            if ($object->hasBeenLoadedFromDb() && $rule->purge_existing === 'y') {
                $found = false;
                foreach ($sources as $source) {
                    if (array_key_exists($object->$objectKey, $imported[$source->id])) {
                        $found = true;
                        break;
                    }
                }

                if (! $found) {
                    $object->markForRemoval();
                }
            }

            // TODO: This should be noticed or removed:
            if (! $object->$objectKey) {
                $ignore[] = $key;
            }
        }

        foreach ($ignore as $key) {
            unset($objects[$key]);
        }

        return $objects;
    }

    /**
     * Runs a SyncRule and applies all resulting changes
     *
     * TODO: Should return the id of the related sync_history table entry.
     *       Such a table does not yet exist, so 42 is the answer right now.
     *
     * @param  SyncRule $rule The synchronization rule that should be applied
     *
     * @return int
     */
    protected function runWithRule(SyncRule $rule)
    {
        $db = $rule->getConnection();
        // TODO: Evaluate whether fetching data should happen within the same transaction
        $objects = $this->prepareSyncForRule($rule);
        $dba = $db->getDbAdapter();
        $dba->beginTransaction();
        foreach ($objects as $object) {
            if ($object instanceof IcingaObject && $object->isTemplate()) {
                if ($object->hasBeenModified()) {
                    throw new IcingaException(
                        'Sync is not allowed to modify template "%s"',
                        $object->$objectKey
                    );
                }
                continue;
            }
            if ($object->shouldBeRemoved()) {
                $object->delete($db);
                continue;
            }

            if ($object->hasBeenModified()) {
                $object->store($db);
            }
        }

        $dba->commit();
        return 42; // We have no sync_run history table yet
    }
}
