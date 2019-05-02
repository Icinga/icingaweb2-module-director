<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\IcingaServiceSet;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;

class IcingaServiceSetHostTable extends ZfQueryBasedTable
{
    protected $set;

    protected $searchColumns = array(
        'host',
    );

    public static function load(IcingaServiceSet $set)
    {
        $table = new static($set->getConnection());
        $table->set = $set;
        return $table;
    }

    public function renderRow($row)
    {
        return $this::row([
            Link::create(
                $row->host,
                'director/host',
                ['name' => $row->host]
            )
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Hostname'),
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['h' => 'icinga_host'],
            [
                'id'          => 'h.id',
                'host'        => 'h.object_name',
                'object_type' => 'h.object_type',
            ]
        )->joinLeft(
            ['ssh' => 'icinga_service_set'],
            'ssh.host_id = h.id',
            []
        )->joinLeft(
            ['ssih' => 'icinga_service_set_inheritance'],
            'ssih.service_set_id = ssh.id',
            []
        )->where(
            'ssih.parent_service_set_id = ?',
            $this->set->id
        )->order('h.object_name');
    }
}
