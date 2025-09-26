<?php

namespace Icinga\Module\Director\Clicommands;

use GuzzleHttp\Psr7\ServerRequest;
use Icinga\Module\Director\Cli\ObjectsCommand;
use Icinga\Module\Director\Forms\CustomPropertiesForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use PDO;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Manage Icinga Hosts
 *
 * Use this command to list Icinga Host objects
 */
class HostsCommand extends ObjectsCommand
{
    public function refreshCustomVarsAction(): void
    {
        foreach ($this->getObjects() as $o) {
            $vars = $o->vars();
            $objectProperties = $this->getObjectCustomProperties($o);

            foreach ($objectProperties as $key => $property) {
                $var = $vars->get($key);
                if ($var && $property['uuid'] !== null) {
                    $var->setUuid(Uuid::fromBytes($property['uuid']));
                    $vars->set($key, $var);
                }
            }

            $vars->storeToDb($o);
        }
    }

    private function getObjectCustomProperties(IcingaObject $object)
    {
        if ($object->uuid === null) {
            return [];
        }

        $type = $object->getShortTableName();

        $parents = $object->listAncestorIds();

        $uuids = [];
        $db = $object->getConnection();

        foreach ($parents as $parent) {
            $uuids[] = IcingaHost::loadWithAutoIncId($parent, $db)->get('uuid');
        }

        $uuids[] = (int) $object->get('uuid');
        $query = $db->getDbAdapter()
                    ->select()
                    ->from(
                        ['dp' => 'director_property'],
                        [
                            'key_name' => 'dp.key_name',
                            'uuid' => 'dp.uuid',
                            $type . '_uuid' => 'iop.' . $type . '_uuid',
                            'value_type' => 'dp.value_type',
                            'label' => 'dp.label',
                            'children' => 'COUNT(cdp.uuid)'
                        ]
                    )
                    ->join(['iop' => "icinga_$type" . '_property'], 'dp.uuid = iop.property_uuid', [])
                    ->joinLeft(['cdp' => 'director_property'], 'cdp.parent_uuid = dp.uuid', [])
                    ->where('iop.' . $type . '_uuid IN (?)', $uuids)
                    ->group(['dp.uuid', 'dp.key_name', 'dp.value_type', 'dp.label'])
                    ->order(
                        "FIELD(dp.value_type, 'string', 'number', 'bool', 'fixed-array',"
                        . " 'dynamic-array', 'fixed-dictionary', 'dynamic-dictionary')"
                    )
                    ->order('children')
                    ->order('key_name');

        $result = [];
        foreach ($db->getDbAdapter()->fetchAll($query, fetchMode: PDO::FETCH_ASSOC) as $row) {
            $result[$row['key_name']] = $row;
        }

        return $result;
    }
}
