<?php

namespace Icinga\Module\Director\Web\Table;

use ipl\Html\Html;
use ipl\Web\Table\Extension\MultiSelect;
use ipl\Html\Link;
use ipl\Web\Url;

class ObjectsTableService extends ObjectsTable
{
    use MultiSelect;

    protected $type = 'service';

    protected $searchColumns = [
        'o.object_name',
        'h.object_name'
    ];

    public function getColumns()
    {
        return [
            'object_name'   => 'o.object_name',
            'disabled'      => 'o.disabled',
            'host'          => 'h.object_name',
            'host_disabled' => 'h.disabled',
            'id'            => 'o.id',
        ];
    }

    public function assemble()
    {
        $this->enableMultiSelect(
            'director/services/edit',
            'director/services',
            ['id']
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
        $url = Url::fromPath('director/service/edit', [
            'name' => $row->object_name,
            'host' => $row->host,
            'id'   => $row->id,
        ]);

        $caption = $row->host === null
            ? Html::span(['class' => 'error'], '- none -')
            : $row->host;

        $hostField = static::td(Link::create($caption, $url));
        if ($row->host === null) {
            $hostField->attributes()->add('class', 'error');
        }
        $tr = static::tr([
            $hostField,
            static::td($row->object_name)
        ]);

        if ($row->host_disabled === 'y' || $row->disabled === 'y') {
            $tr->attributes()->add('class', 'disabled');
        }
        return $tr;
    }

    public function prepareQuery()
    {
        return parent::prepareQuery()->joinLeft(
            ['h' => 'icinga_host'],
            'o.host_id = h.id',
            []
        )->order('o.object_name')->order('h.object_name');
    }
}
