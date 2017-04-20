<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db;
use ipl\Html\Link;
use ipl\Html\Table;
use ipl\Web\Url;

class ServicesOnHostsTable extends Table
{
    protected $defaultAttributes = [
        'class' => ['simple', 'common-table', 'table-row-selectable', 'multiselect'],
        'data-base-target' => '_next',
    ];

    private $db;

    public function __construct(Db $connection)
    {
        $this->db = $connection->getDbAdapter();
        $this->addMultiSelectAttributes();
        $this->header();
        $this->fetchRows();
    }

    public function getColumnsToBeRendered()
    {
        return ['Service Name', 'Host'];
    }

    protected function addMultiSelectAttributes()
    {
        $props = $this->getMultiselectProperties();

        if (empty($props)) {
            return $this;
        }

        $prefix = 'data-icinga-multiselect';
        $multi = [
            "$prefix-url"         => Url::fromPath($props['url']),
            "$prefix-controllers" => Url::fromPath($props['sourceUrl']),
            "$prefix-data"        => implode(',', $props['keys']),
        ];

        $this->addAttributes($multi);

        return $this;
    }

    protected function getMultiselectProperties()
    {
        return [
            'url'       => 'director/services/edit',
            'sourceUrl' => 'director/service/edit',
            // TODO: evaluate 'keys' => ['name', 'host'],
            'keys'      => ['id'],
        ];
    }
    protected function fetchRows()
    {
        $body = $this->body();
        foreach ($this->fetch() as $row) {
            $body->add($this->renderRow($row));
        }
    }

    public function renderRow($row)
    {
        $url = Url::fromPath('director/service/edit', [
            'name' => $row->service,
            'host' => $row->host,
            'id'   => $row->id,
        ]);

        return static::tr([
            static::td(Link::create($row->host, $url)),
            static::td($row->service)
        ]);
    }

    public function fetch()
    {
        return $this->db->fetchAll(
            $this->prepareQuery()
        );
    }

    public function prepareQuery()
    {
        $columns = [
            'host'    => 'h.object_name',
            'service' => 's.object_name',
            'id'      => 's.id',
        ];
        $query = $this->db->select()->from(
            ['s' => 'icinga_service'],
            $columns
        )->join(
            ['h' => 'icinga_host'],
            "s.host_id = h.id AND h.object_type = 'object'",
            []
        );

        return $query;
    }
}
