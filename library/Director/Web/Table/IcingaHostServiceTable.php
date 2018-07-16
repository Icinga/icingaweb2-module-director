<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\IcingaHost;
use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;

class IcingaHostServiceTable extends ZfQueryBasedTable
{
    protected $title;

    /** @var IcingaHost */
    protected $host;

    /** @var IcingaHost */
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
        $table->getAttributes()->set('data-base-target', '_self');
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
        $classes = [];
        if ($row->blacklisted === 'y') {
            $classes[] = 'strike-links';
        }
        if ($row->disabled === 'y') {
            $classes[] = 'disabled';
        }

        $attributes = empty($classes) ? null : ['class' => $classes];

        return $this::row([
            $this->getServiceLink($row)
        ], $attributes);
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

    /**
     * @return \Zend_Db_Select
     */
    public function prepareQuery()
    {
        $db = $this->db();

        $query = $db->select()->from(
            ['s' => 'icinga_service'],
            [
                'id'          => 's.id',
                'host_id'     => 's.host_id',
                'host'        => 'h.object_name',
                'service'     => 's.object_name',
                'disabled'    => 's.disabled',
                'object_type' => 's.object_type',
                'blacklisted' => "CASE WHEN hsb.service_id IS NULL THEN 'n' ELSE 'y' END"
            ]
        )->joinLeft(
            ['h' => 'icinga_host'],
            'h.id = s.host_id',
            []
        )->joinLeft(
            ['hsb' => 'icinga_host_service_blacklist'],
            $db->quoteInto(
                's.id = hsb.service_id AND hsb.host_id = ?',
                $this->inheritedBy === null
                    ? $this->host->get('id')
                    : $this->inheritedBy->get('id')
            ),
            []
        )->where(
            's.host_id = ?',
            $this->host->get('id')
        )->order('s.object_name');

        return $query;
    }
}
