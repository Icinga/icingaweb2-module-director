<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\ObjectsCommand;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use PDO;
use Ramsey\Uuid\Uuid;

/**
 * Manage Icinga Hosts
 *
 * Use this command to list Icinga Host objects
 */
class HostsCommand extends ObjectsCommand
{
    /**
     * Updates the custom variables associated with objects by syncing them with their corresponding
     * custom variable uuids, and stores the updated variables in the database.
     *
     * @return void
     */
    public function refreshCustomVarsAction(): void
    {
        foreach ($this->getObjects() as $o) {
            $vars = $o->vars();
            $objectCustomVariables = $this->getObjectCustomVariables($o);

            foreach ($objectCustomVariables as $key => $property) {
                $var = $vars->get($key);
                if ($var && $property['uuid'] !== null) {
                    $var->setUuid(Uuid::fromBytes($property['uuid']));
                    $vars->set($key, $var);
                }
            }

            $vars->storeToDb($o);
        }
    }

    /**
     * Retrieves custom variables of the given Icinga object.
     *
     * @param IcingaObject $object  The Icinga object whose custom variables need to be fetched.
     *
     * @return array An associative array of custom variables keyed by their names and their configuration details.
     */
    private function getObjectCustomVariables(IcingaObject $object): array
    {
        if ($object->uuid === null) {
            return [];
        }

        $objectType = $object->getShortTableName();

        $parents = $object->listAncestorIds();

        $uuids = [];
        $db = $object->getConnection();

        foreach ($parents as $parent) {
            $uuids[] = DbUtil::binaryResult(IcingaHost::loadWithAutoIncId($parent, $db)->get('uuid'));
        }

        $uuids[] = DbUtil::binaryResult($object->get('uuid'));
        $types = [
            'string',
            'sensitive',
            'number',
            'bool',
            'fixed-array',
            'dynamic-array',
            'fixed-dictionary',
            'dynamic-dictionary'
        ];

        if ($db->isPgsql()) {
            $cases = [];
            foreach ($types as $i => $type) {
                $cases[] = "WHEN '$type' THEN " . ($i + 1);
            }

            $valueTypeOrder = 'CASE dp.value_type ' . implode(' ', $cases) . ' ELSE ' . (count($types) + 1) . ' END';
        } else {
            $valueTypeOrder = "FIELD(dp.value_type, '" . implode("', '", $types) . "')";
        }

        $query = $db->getDbAdapter()
                    ->select()
                    ->from(
                        ['dp' => 'director_property'],
                        [
                            'key_name' => 'dp.key_name',
                            'uuid' => 'dp.uuid',
                            $objectType . '_uuid' => 'iop.' . $objectType . '_uuid',
                            'value_type' => 'dp.value_type',
                            'label' => 'dp.label',
                            'children' => 'COUNT(cdp.uuid)'
                        ]
                    )
                    ->join(['iop' => "icinga_$objectType" . '_property'], 'dp.uuid = iop.property_uuid', [])
                    ->joinLeft(['cdp' => 'director_property'], 'cdp.parent_uuid = dp.uuid', [])
                    ->where(
                        'iop.' . $objectType . '_uuid IN (?)',
                        DbUtil::quoteBinaryCompat($uuids, $db->getDbAdapter())
                    )
                    ->group(['dp.uuid', 'dp.key_name', 'dp.value_type', 'dp.label'])
                    ->order($valueTypeOrder)
                    ->order('children')
                    ->order('key_name');

        $result = [];
        foreach ($db->getDbAdapter()->fetchAll($query, fetchMode: PDO::FETCH_ASSOC) as $row) {
            $result[$row['key_name']] = DbUtil::normalizeRow($row);
        }

        return $result;
    }
}
