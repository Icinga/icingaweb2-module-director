<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Forms\RemoveLinkForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use dipl\Html\HtmlElement;
use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;
use dipl\Web\Url;

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
        $table->getAttributes()->set('data-base-target', '_self');
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

    protected function addHeaderColumnsTo(HtmlElement $parent)
    {
        if ($this->host || $this->affectedHost) {
            $this->addHostHeaderTo($parent);
        } else {
            parent::addHeaderColumnsTo($parent);
        }

        return $parent;
    }

    /**
     * @param $row
     * @return Link
     */
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
            $tr->getAttributes()->add('class', 'disabled');
        }
        if ($row->blacklisted === 'y') {
            $tr->getAttributes()->add('class', 'strike-links');
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

    /**
     * @param HtmlElement $parent
     */
    protected function addHostHeaderTo(HtmlElement $parent)
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
            $deleteLink = new RemoveLinkForm(
                $this->translate('Remove'),
                sprintf(
                    $this->translate('Remove "%s" from this host'),
                    $this->getTitle()
                ),
                Url::fromPath('director/host/services', [
                    'name' => $this->host->getObjectName()
                ]),
                ['title' => $this->getTitle()]
            );
            $deleteLink->runOnSuccess(function () {
                $conn = $this->set->getConnection();
                $db = $conn->getDbAdapter();
                $query = $db->select()->from(
                    ['ss' => 'icinga_service_set'],
                    'ss.id'
                )->join(
                    ['ssih' => 'icinga_service_set_inheritance'],
                    'ssih.service_set_id = ss.id',
                    []
                )->where(
                    'ssih.parent_service_set_id = ?',
                    $this->set->get('id')
                )->where('ss.host_id = ?', $this->host->get('id'));
                IcingaServiceSet::loadWithAutoIncId(
                    $db->fetchOne($query),
                    $conn
                )->delete();
            });
            $deleteLink->handleRequest();
        }

        $parent->add($this::th([$this->getTitle(), $deleteLink]));
    }

    /**
     * @return \Zend_Db_Select
     * @throws \Zend_Db_Select_Exception
     */
    public function prepareQuery()
    {
        $db = $this->db();
        $query = $db->select()->from(
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

        if ($this->affectedHost) {
            $query->joinLeft(
                ['hsb' => 'icinga_host_service_blacklist'],
                $db->quoteInto(
                    's.id = hsb.service_id AND hsb.host_id = ?',
                    $this->affectedHost->get('id')
                ),
                []
            )->columns([
                'blacklisted' => "CASE WHEN hsb.service_id IS NULL THEN 'n' ELSE 'y' END",
            ]);
        } else {
            $query->columns(['blacklisted' => "('n')"]);
        }

        return $query;
    }
}
