<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaZone extends IcingaObject
{
    protected $table = 'icinga_zone';

    protected $defaultProperties = array(
        'id'          => null,
        'object_name' => null,
        'object_type' => null,
        'disabled'    => 'n',
        'parent_id'   => null,
        'is_global'   => 'n',
    );

    protected $booleans = array(
        // Global is a reserved word in SQL, column name was prefixed
        'is_global' => 'global'
    );

    protected $relations = array(
        'parent' => 'IcingaZone',
    );

    protected $supportsImports = true;

    private $endpointList;

    protected function renderCustomExtensions()
    {
        $endpoints = $this->listEndpoints();
        if (empty($endpoints)) {
            return '';
        }

        return c::renderKeyValue('endpoints', c::renderArray($endpoints));
    }

    public function setEndpointList($list)
    {
        $this->endpointList = $list;
        return $this;
    }

    // TODO: Move this away, should be prefetchable:
    protected function listEndpoints()
    {
        if ($this->id && $this->endpointList === null) {
            $db = $this->getDb();
            $query = $db->select()
                ->from('icinga_endpoint', 'object_name')
                ->where('zone_id = ?', $this->id)
                ->order('object_name');

            $this->endpointList = $db->fetchCol($query);
        }

        return $this->endpointList;
    }
}
