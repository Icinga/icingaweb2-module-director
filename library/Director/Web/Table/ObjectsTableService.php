<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db\DbUtil;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Table\Extension\MultiSelect;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Url;
use Ramsey\Uuid\Uuid;

class ObjectsTableService extends ObjectsTable
{
    use MultiSelect;

    protected $type = 'service';

    protected $columns = [
        'object_name'      => 'o.object_name',
        'disabled'         => 'o.disabled',
        'host'             => 'h.object_name',
        'host_object_type' => 'h.object_type',
        'host_disabled'    => 'h.disabled',
        'id'               => 'o.id',
        'uuid'            => 'o.uuid',
        'blacklisted'      => "CASE WHEN hsb.service_id IS NULL THEN 'n' ELSE 'y' END",
    ];

    protected $searchColumns = [
        'o.object_name',
        'h.object_name'
    ];

    public function assemble()
    {
        $this->enableMultiSelect(
            'director/services/edit',
            'director/services',
            ['uuid']
        );
    }

    public function getColumnsToBeRendered()
    {
        return [
            'host'        => 'Host',
            'object_name' => 'Service Name'
        ];
    }

    public function renderRow($row)
    {
        $params = [
            'uuid' => Uuid::fromBytes(DbUtil::binaryResult($row->uuid))->toString(),
        ];
        if ($row->host !== null) {
            $params['host'] = $row->host;
        }
        $url = Url::fromPath('director/service/edit', $params);
        /*
        if ($this->branchUuid) {
            $url = Url::fromPath('director/service/edit', [
                'uuid' => Uuid::fromBytes(DbUtil::binaryResult($row->uuid))->toString(),
                'host' => $row->host,
            ]);
        } else {
            $url = Url::fromPath('director/service/edit', [
                'name' => $row->object_name,
                'host' => $row->host,
                'id'   => $row->id,
            ]);
        }
        */

        $caption = $row->host === null
            ? Html::tag('span', ['class' => 'error'], '- none -')
            : $row->host;

        $hostField = static::td(Link::create($caption, $url));
        if ($row->host === null) {
            $hostField->getAttributes()->add('class', 'error');
        }
        $tr = static::tr([
            $hostField,
            static::td($row->object_name)
        ]);

        $attributes = $tr->getAttributes();
        $classes = $this->getRowClasses($row);
        if ($row->host_disabled === 'y' || $row->disabled === 'y') {
            $classes[] = 'disabled';
        }
        if ($row->blacklisted === 'y') {
            $classes[] = 'strike-links';
        }
        $attributes->add('class', $classes);

        return $tr;
    }

    public function prepareQuery()
    {
        $query = parent::prepareQuery();
        if ($this->branchUuid) {
            $queries = [$this->leftSubQuery, $this->rightSubQuery];
        } else {
            $queries = [$query];
        }

        foreach ($queries as $subQuery) {
            $subQuery->joinLeft(
                ['h' => 'icinga_host'],
                'o.host_id = h.id',
                []
            )->joinLeft(
                ['hsb' => 'icinga_host_service_blacklist'],
                'hsb.service_id = o.id AND hsb.host_id = o.host_id',
                []
            )->where('o.service_set_id IS NULL')
                ->order('o.object_name')->order('h.object_name');
        }

        return $query;
    }
}
