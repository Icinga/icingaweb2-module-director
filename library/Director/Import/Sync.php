<?php

namespace Icinga\Module\Director\Import;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\SyncRule;

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

    protected function extractVariableNames($string)
    {
        if (preg_match_all('/\${([A-Za-z0-9_-]+)}/', $string, $m, PREG_PATTERN_ORDER)) {
            return $m[1];
        } else {
            return array();
        }
    }

    protected function fillVariables($string, $row)
    {
        $func = function ($match) use ($row) {
            return $row->{$match[1]};
        };

        return preg_replace_callback('/\${([A-Za-z0-9_-]+)}/', $func, $string);
    }

    protected function runWithRule(SyncRule $rule)
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
                    throw new \Exception(
                        sprintf(
                            'There is no key column "%s" in this row from "%s": %s', $key, $source->source_name, json_encode($row)
                        )
                    );
                }
                $imported[$sourceId][$row->$key] = $row;
            }
        }

        // TODO: Filter auf object, nicht template
        $objects = IcingaHost::loadAll($db, null, 'object_name');

        foreach ($sources as $source) {
            $sourceId = $source->id;

            foreach ($imported[$sourceId] as $key => $row) {
                $newProps = array(
                    'object_type' => 'object',
                    'object_name' => $key
                );

                $newVars = array();
                $imports = array();

                foreach ($properties as $p) {
                    if ($p->source_id !== $sourceId) continue;

                    $prop = $p->destination_field;
                    $val = $this->fillVariables($p->source_expression, $row);

                    if (substr($prop, 0, 5) === 'vars.') {
                        $newVars[substr($prop, 5)] = $val;
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
                            $objects[$key] = IcingaHost::create($newProps);
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
                    $objects[$key] = IcingaHost::create($newProps);
                    foreach ($newVars as $prop => $var) {
                        $objects[$key]->vars()->$prop = $var;
                    }
                }
            }

        }

            $dba = $db->getDbAdapter();
            $dba->beginTransaction();
            foreach ($objects as $object) {
                if ($object->isTemplate()) {
                    if ($object->hasBeenModified()) {
                        throw new \Exception(
                            sprintf(
                                'Sync is not allowed to modify template "%s"',
                                $object->object_name
                            )
                        );
                    }

                    continue;
                }

                if ($object->hasBeenLoadedFromDb() && $rule->purge_existing === 'y') {
                    $found = false;
                    foreach ($sources as $source) {
                        if (array_key_exists($object->object_name, $imported[$source->id])) {
                            $found = true;
                            break;
                        }
                    }

                    if (! $found) {
                        $object->delete();
                    }
                }
if (! $object->object_name) {
continue;
}
                $object->store($db);
            }

            $dba->commit();
        return 42; // We have no sync_run history table yet
    }
}
