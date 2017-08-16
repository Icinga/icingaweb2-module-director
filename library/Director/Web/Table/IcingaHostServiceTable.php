<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\IcingaHost;
use ipl\Html\Link;
use ipl\Web\Table\ZfQueryBasedTable;

class IcingaHostServiceTable extends ZfQueryBasedTable
{
    protected $title;

    /** @var IcingaHost */
    protected $host;

    protected $inheritedBy;

    protected $searchColumns = [
        'service',
    ];

    /**
     * @param IcingaHost $host
     * @return static
     */
    public static function load(IcingaHost $host)
    {
        $table = new static($host->getConnection());
        $table->setHost($host);
        $table->attributes()->set('data-base-target', '_self');
        return $table;
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
    }

    public function setInheritedBy(IcingaHost $host)
    {
        $this->inheritedBy = $host;
        return $this;
    }

    public function renderRow($row)
    {
        return $this::row([
            $this->getServiceLink($row)
        ]);
    }

    protected function getServiceLink($row)
    {
        if ($target = $this->inheritedBy) {
            $params = array(
                'name'          => $target->object_name,
                'service'       => $row->service,
                'inheritedFrom' => $row->host,
            );

            return Link::create(
                $row->service,
                'director/host/inheritedservice',
                $params
            );
        }

        if ($row->object_type === 'apply') {
            $params['id'] = $row->id;
        } else {
            $params = array('name' => $row->service);
            if ($row->host !== null) {
                $params['host'] = $row->host;
            }
        }

        return Link::create(
            $row->service,
            'director/service/edit',
            $params
        );
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->title ?: $this->translate('Servicename'),
        ];
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['s' => 'icinga_service'],
            [
                'id'          => 's.id',
                'host_id'     => 's.host_id',
                'host'        => 'h.object_name',
                'service'     => 's.object_name',
                'object_type' => 's.object_type',
            ]
        )->joinLeft(
            ['h' => 'icinga_host'],
            'h.id = s.host_id',
            []
        )->where(
            's.host_id = ?',
            $this->host->get('id')
        )->order('s.object_name');
    }
}
