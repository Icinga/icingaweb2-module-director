<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use ipl\Html\Element;
use ipl\Html\Link;
use ipl\Web\Table\ZfQueryBasedTable;

class IcingaServiceSetServiceTable extends ZfQueryBasedTable
{
    /** @var IcingaServiceSet */
    protected $set;

    protected $title;

    /** @var IcingaHost */
    protected $host;

    /** @var IcingaHost */
    protected $affectedHost;

    protected $searchColumns = [
        'service',
    ];

    /**
     * @param IcingaServiceSet $set
     * @return static
     */
    public static function load(IcingaServiceSet $set)
    {
        $table = new static($set->getConnection());
        $table->set = $set;
        $table->attributes()->set('data-base-target', '_self');
        return $table;
    }

    /**
     * @param string $title
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @param IcingaHost $host
     * @return $this
     */
    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * @param IcingaHost $host
     * @return $this
     */
    public function setAffectedHost(IcingaHost $host)
    {
        $this->affectedHost = $host;
        return $this;
    }

    protected function addHeaderColumnsTo(Element $parent)
    {
        if ($this->host || $this->affectedHost) {
            $this->addHostHeaderTo($parent);
        } else {
            parent::addHeaderColumnsTo($parent);
        }

        return $parent;
    }

    protected function getServiceLink($row)
    {
        if ($this->affectedHost) {
            $params = [
                'name'    => $this->affectedHost->getObjectName(),
                'service' => $row->service,
                'set'     => $row->service_set
            ];
            $url = 'director/host/servicesetservice';
        } else {
            $params = [
                'name' => $row->service,
                'set'  => $row->service_set
            ];
            $url = 'director/service';
        }

        return Link::create(
            $row->service,
            $url,
            $params
        );
    }

    public function renderRow($row)
    {
        $tr = $this::row([
            $this->getServiceLink($row)
        ]);

        if ($row->disabled === 'y') {
            $tr->attributes()->add('class', 'disabled');
        }

        return $tr;
    }

    public function getColumnsToBeRendered()
    {
        return ['Will not be rendered'];
    }

    protected function getTitle()
    {
        return $this->title ?: $this->translate('Servicename');
    }

    protected function addHostHeaderTo(Element $parent)
    {
        if (! $this->host) {
            $deleteLink = '';
        } elseif ($this->affectedHost->get('id') !== $this->host->get('id')) {
            $host = $this->host;
            $deleteLink = Link::create(
                $host->getObjectName(),
                'director/host/services',
                ['name' => $host->getObjectName()],
                [
                    'class' => 'icon-paste',
                    'style' => 'float: right; font-weight: normal',
                    'data-base-target' => '_next',
                    'title' => sprintf(
                        $this->translate('This set has been inherited from %s'),
                        $host->getObjectName()
                    )
                ]
            );
        } else {
            $deleteLink = Link::create(
                $this->translate('Remove'),
                'director/host/removeset',
                [
                    'name' => $this->host->getObjectName(),
                    'setId' => $this->set->get('id')
                ],
                [
                    'class' => 'icon-cancel',
                    'style' => 'float: right; font-weight: normal',
                    'title' => $this->translate('Remove this set from this host')
                ]
            );
        }

        $parent->add($this::th([$this->getTitle(), $deleteLink]));
    }

    public function prepareQuery()
    {
        return $this->db()->select()->from(
            ['s' => 'icinga_service'],
            [
                'id'             => 's.id',
                'service_set_id' => 's.service_set_id',
                'host_id'        => 'ss.host_id',
                'service_set'    => 'ss.object_name',
                'service'        => 's.object_name',
                'disabled'       => 's.disabled',
                'object_type'    => 's.object_type',
            ]
        )->joinLeft(
            ['ss' => 'icinga_service_set'],
            'ss.id = s.service_set_id',
            []
        )->where(
            's.service_set_id = ?',
            $this->set->get('id')
        )->order('s.object_name');
    }
}
