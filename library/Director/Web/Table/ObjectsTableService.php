<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\IcingaObjectFilterHelper;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Table\Extension\MultiSelect;
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
    protected function assemble()
    {
        $this->enableMultiSelect(
            'director/services/edit',
            'director/services',
            ['name', 'host']
        );
    }

    public function filterTemplate(
        IcingaService $template,
        $inheritance = IcingaObjectFilterHelper::INHERIT_DIRECT
    ) {
        IcingaObjectFilterHelper::filterByTemplate(
            $this->getQuery(),
            $template,
            'o',
            $inheritance
        );

        return $this;
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

        $tr = static::tr([
            static::td(Link::create($row->host, $url)),
            static::td($row->object_name)
        ]);

        if ($row->host_disabled === 'y' || $row->disabled === 'y') {
            $tr->attributes()->add('class', 'disabled');
        }
        return $tr;
    }

    public function prepareQuery()
    {
        return parent::prepareQuery()->join(
            ['h' => 'icinga_host'],
            "o.host_id = h.id AND h.object_type = 'object'",
            []
        )->order('o.object_name')->order('h.object_name');
    }
}
