<?php

namespace Icinga\Module\Director\Import;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;
use Icinga\Exception\IcingaException;

class Sync
{
    protected function __construct()
    {
    }

    public static function run(SyncRule $rule)
    {
        $sync = new static;
        return $sync->runWithRule($rule);
    }

    public static function hasModifications(SyncRule $rule)
    {
        return count(self::getExpectedModifications($rule)) > 0;
    }

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

    protected function extractVariableNames($string)
    {
        if (preg_match_all('/\${([A-Za-z0-9\._-]+)}/', $string, $m, PREG_PATTERN_ORDER)) {
            return $m[1];
        } else {
            return array();
        }
    }

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

    protected function fillVariables($string, $row)
    {
        if (preg_match('/^\${([A-Za-z0-9\._-]+)}$/', $string, $m)) {
            $var = $m[1];
            if (strpos($var, '.') === false) {
                if (! property_exists($row, $var)) {
                    return null;
                }

                $val = $row->$var;
            } else {
                $parts = explode('.', $var);
                $main = array_shift($parts);
                if (! is_object($row->$main)) {
                    die('Data is not nested, cannot access ...');
                }

                return $this->getDeepValue($row->$main, $parts);
            }

            return $val;
        }

        $func = function ($match) use ($row) {
            // TODO allow to access deep value also here
            return $row->{$match[1]};
        };

        return preg_replace_callback('/\${([A-Za-z0-9\._-]+)}/', $func, $string);
    }

    protected function prepareSyncForRule(SyncRule $rule)
    {
        $db = $rule->getConnection();
        $properties = $rule->fetchSyncProperties();
        $sourceColumns = array();
        $sources = array();
        // $fieldMap = array();

        foreach ($properties as $p) {
            $sourceId = $p->source_id;
            if (! array_key_exists($sourceId, $sources)) {
                $sources[$sourceId] = ImportSource::load($sourceId, $db);
                $sourceColumns[$sourceId] = array();
            }

            foreach ($this->extractVariableNames($p->source_expression) as $varname) {
                $sourceColumns[$sourceId][$varname] = $varname;
                // -> ? $fieldMap[
            }
        }

        $imported = array();
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
                $imported[$sourceId][$row->$key] = $row;
            }
        }

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
                    // TODO: temporarily disabled, "mark" them: $object->delete();
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
            if ($object->hasBeenModified()) {
                $object->store($db);
            }

        }

        $dba->commit();
        return 42; // We have no sync_run history table yet
    }
}
