<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;

class IcingaZone extends IcingaObject
{
    protected $table = 'icinga_zone';

    protected $defaultProperties = array(
        'id'             => null,
        'object_name'    => null,
        'object_type'    => null,
        'parent_zone_id' => null,
        'is_global'      => 'n',
    );

    protected $booleans = array(
        // Global is a reserved word in SQL, column name was prefixed
        'is_global' => 'global'
    );

    protected $relations = array(
        'parent_zone' => 'IcingaZone',
    );

    protected $supportsImports = true;

    protected function renderCustomExtensions()
    {
        $endpoints = $this->listEndpoints();
        if (empty($endpoints)) {
            return '';
        }

        return c::renderKeyValue('endpoints', c::renderArray($endpoints));
    }

    // TODO: Move this away, should be prefetchable:
    protected function listEndpoints()
    {
        $db = $this->getDb();
        $query = $db->select()
            ->from('icinga_endpoint', 'object_name')
            ->where('zone_id = ?', $this->id)
            ->order('object_name');

        return $db->fetchCol($query);
    }

    protected function renderParent_zone_id()
    {
        return $this->renderRelationProperty(
            'parent_zone',
            $this->parent_zone_id,
            'parent'
        );
    }
}
