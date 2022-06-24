<?php

namespace Icinga\Module\Director\Data;

use gipfl\ZfDb\Adapter\Adapter;
use Icinga\Module\Director\Data\Db\DbObject;
use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDatafield;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Objects\DirectorDatalistEntry;
use Icinga\Module\Director\Objects\DirectorJob;
use Icinga\Module\Director\Objects\IcingaCommand;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Objects\IcingaTemplateChoice;
use Icinga\Module\Director\Objects\ImportRowModifier;
use Icinga\Module\Director\Objects\ImportSource;
use Icinga\Module\Director\Objects\InstantiatedViaHook;
use Icinga\Module\Director\Objects\SyncRule;
use RuntimeException;

class Exporter
{
    protected static $denyProperties = [
        DirectorJob::class => [
            'last_attempt_succeeded',
            'last_error_message',
            'ts_last_attempt',
            'ts_last_error',
        ],
        ImportSource::class => [
            // No state export
            'import_state',
            'last_error_message',
            'last_attempt',
        ],
        ImportRowModifier::class => [
            // Not state, but to be removed:
            'source_id',
        ],
        SyncRule::class => [
            'sync_state',
            'last_error_message',
            'last_attempt',
        ],
    ];

    /** @var Adapter|\Zend_Db_Adapter_Abstract */
    protected $db;

    /** @var FieldReferenceLoader */
    protected $fieldReferenceLoader;

    public function __construct(Db $connection)
    {
        $this->db = $connection->getDbAdapter();
        $this->fieldReferenceLoader = new FieldReferenceLoader($connection);
    }

    public function export(DbObject $object)
    {
        $props = $object instanceof IcingaObject
            ? $this->exportIcingaObject($object)
            : $this->exportDbObject($object);

        $this->stripDeniedProperties($props, $object);
        $this->appendTypeSpecificRelations($props, $object);

        ksort($props);
        return (object) $props;
    }

    protected function appendTypeSpecificRelations(array &$props, DbObject $object)
    {
        if ($object instanceof DirectorDatalist) {
            $props['entries'] = $this->exportDatalistEntries($object);
        } elseif ($object instanceof DirectorDatafield) {
            if (isset($props['settings']->datalist_id)) {
                $props['settings']->datalist = $this->getDatalistNameForId($props['settings']->datalist_id);
                unset($props['settings']->datalist_id);
            }

            $props['category'] = isset($props['category_id'])
                ? $this->getDatafieldCategoryNameForId($props['category_id'])
                : null;
            unset($props['category_id']);
        } elseif ($object instanceof ImportSource) {
            $props['modifiers'] = $this->exportRowModifiers($object);
        } elseif ($object instanceof SyncRule) {
            $props['properties'] = $this->exportSyncProperties($object);
        } elseif ($object instanceof IcingaCommand) {
            if (isset($props['arguments'])) {
                foreach ($props['arguments'] as $key => $argument) {
                    if (property_exists($argument, 'command_id')) {
                        unset($props['arguments'][$key]->command_id);
                    }
                }
            }
        } elseif ($object instanceof DirectorJob) {
            if ($object->hasTimeperiod()) {
                $props['timeperiod'] = $object->timeperiod()->getObjectName();
            }
            unset($props['timeperiod_id']);
        } elseif ($object instanceof IcingaTemplateChoice) {
            if (isset($props['required_template_id'])) {
                $requiredId = $props['required_template_id'];
                unset($props['required_template_id']);
                $props = $this->loadTemplateName($object->getObjectTableName(), $requiredId);
            }

            $props['members'] = array_values($object->getMembers());
        } elseif ($object instanceof IcingaServiceSet) {
            if ($object->get('host_id')) {
                // Sets on Host
                throw new RuntimeException('Not yet');
            }
            $props['services'] = [];
            foreach ($object->getServiceObjects() as $serviceObject) {
                $props['services'][$serviceObject->getObjectName()] = $this->export($serviceObject);
            }
            ksort($props['services']);
        }
    }

    protected function loadTemplateName($table, $id)
    {
        $db = $this->db;
        $query = $db->select()
            ->from(['o' => $table], 'o.object_name')->where("o.object_type = 'template'")
            ->where('o.id = ?', $id);

        return $db->fetchOne($query);
    }

    protected function getDatalistNameForId($id)
    {
        $db = $this->db;
        $query = $db->select()->from('director_datalist', 'list_name')->where('id = ?', (int) $id);
        return $db->fetchOne($query);
    }

    protected function getDatafieldCategoryNameForId($id)
    {
        $db = $this->db;
        $query = $db->select()->from('director_datafield_category', 'category_name')->where('id = ?', (int) $id);
        return $db->fetchOne($query);
    }

    protected function stripDeniedProperties(array &$props, DbObject $object)
    {
        // TODO: this used to exist. Double-check all imports to verify it's not in use
        // $originalId = $props['id'];
        unset($props['id']);
        $class = get_class($object);
        if (isset(self::$denyProperties[$class])) {
            foreach (self::$denyProperties[$class] as $key) {
                unset($props[$key]);
            }
        }

    }

    protected function exportRowModifiers(ImportSource $object)
    {
        $modifiers = [];
        // Hint: they're sorted by priority
        foreach ($object->fetchRowModifiers() as $modifier) {
            $modifiers[] = $this->export($modifier);
        }

        return $modifiers;
    }


    public function exportSyncProperties(SyncRule $object)
    {
        $all = [];
        $db = $this->db;
        $sourceNames = $db->fetchPairs(
            $db->select()->from('import_source', ['id', 'source_name'])
        );

        foreach ($object->getSyncProperties() as $property) {
            $properties = $property->getProperties();
            $properties['source'] = $sourceNames[$properties['source_id']];
            unset($properties['id']);
            unset($properties['rule_id']);
            unset($properties['source_id']);
            ksort($properties);
            $all[] = (object) $properties;
        }

        return $all;
    }

    /**
     * @param DbObject $object
     * @return array
     */
    protected function exportDbObject(DbObject $object)
    {
        $props = $object->getProperties();
        if ($object instanceof DbObjectWithSettings) {
            if ($object instanceof InstantiatedViaHook) {
                $props['settings'] = (object) $object->getInstance()->exportSettings();
            } else {
                $props['settings'] = (object) $object->getSettings(); // Already sorted
            }
        }

        return $props;
    }

    /**
     * @param IcingaObject $object
     * @return array
     * @throws \Icinga\Exception\NotFoundError
     */
    protected function exportIcingaObject(IcingaObject $object)
    {
        $props = (array) $object->toPlainObject();
        if ($object->supportsFields()) {
            $props['fields'] = $this->fieldReferenceLoader->loadFor($object);
        }

        return $props;
    }

    protected function exportDatalistEntries(DirectorDatalist $list)
    {
        $entries = [];
        $id = $list->get('id');
        if ($id === null) {
            return $entries;
        }

        $dbEntries = DirectorDatalistEntry::loadAllForList($list);
        // Hint: they are loaded with entry_name key
        ksort($dbEntries);

        foreach ($dbEntries as $entry) {
            if ($entry->shouldBeRemoved()) {
                continue;
            }
            $plainEntry = $entry->getProperties();
            unset($plainEntry['list_id']);

            $entries[] = $plainEntry;
        }

        return $entries;
    }
}
