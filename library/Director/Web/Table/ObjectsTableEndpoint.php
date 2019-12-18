<?php

namespace Icinga\Module\Director\Web\Table;

use gipfl\IcingaWeb2\Icon;
use Zend_Db_Select as ZfSelect;

class ObjectsTableEndpoint extends ObjectsTable
{
    protected $searchColumns = [
        'o.object_name',
    ];

    protected $deploymentEndpoint;

    public function getColumnsToBeRendered()
    {
        return array(
            'object_name' => $this->translate('Endpoint'),
            'host'        => $this->translate('Host'),
            'zone'        => $this->translate('Zone'),
            'object_type' => $this->translate('Type'),
        );
    }

    public function getColumns()
    {
        return [
            'object_name' => 'o.object_name',
            'object_type' => 'o.object_type',
            'disabled'    => 'o.disabled',
            'host' => "(CASE WHEN o.host IS NULL THEN NULL ELSE"
                . " CONCAT(o.host || ':' || COALESCE(o.port, 5665)) END)",
            'zone' => 'z.object_name',
        ];
    }

    protected function getMainLinkLabel($row)
    {
        if ($row->object_name === $this->deploymentEndpoint) {
            return [
                $row->object_name,
                ' ',
                Icon::create('upload', [
                    'title' => $this->translate(
                        'This is your Config master and will receive our Deployments'
                    )
                ])
            ];
        } else {
            return $row->object_name;
        }
    }

    public function getRowClasses($row)
    {
        if ($row->object_name === $this->deploymentEndpoint) {
            return array_merge(array('deployment-endpoint'), parent::getRowClasses($row));
        } else {
            return null;
        }
    }

    protected function applyObjectTypeFilter(ZfSelect $query)
    {
        return $query->where("o.object_type IN ('object', 'external_object')");
    }

    public function prepareQuery()
    {
        if ($this->deploymentEndpoint === null) {
            /** @var \Icinga\Module\Director\Db $c */
            $c = $this->connection();
            if ($c->hasDeploymentEndpoint()) {
                $this->deploymentEndpoint = $c->getDeploymentEndpointName();
            }
        }

        return parent::prepareQuery()->joinLeft(
            ['z' => 'icinga_zone'],
            'o.zone_id = z.id',
            []
        );
    }
}
