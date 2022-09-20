<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Data\Db\ServiceSetQueryBuilder;
use Icinga\Module\Director\Db;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use Icinga\Module\Director\Forms\RemoveLinkForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use ipl\Html\HtmlElement;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;

class IcingaServiceSetServiceTable extends ZfQueryBasedTable
{
    use TableWithBranchSupport;

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

    /** @var bool */
    protected $readonly = false;

    /** @var string|null */
    protected $highlightedService;

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

    /**
     * Show no related links
     *
     * @param bool $readonly
     * @return $this
     */
    public function setReadonly($readonly = true)
    {
        $this->readonly = (bool) $readonly;

        return $this;
    }

    public function highlightService($service)
    {
        $this->highlightedService = $service;

        return $this;
    }

    /**
     * @param $row
     * @return BaseHtmlElement
     */
    protected function getServiceLink($row)
    {
        if ($this->readonly) {
            if ($this->highlightedService === $row->service) {
                return Html::tag('span', ['class' => 'ro-service icon-right-big'], $row->service);
            }

            return Html::tag('span', ['class' => 'ro-service'], $row->service);
        }

        if ($this->affectedHost) {
            $params = [
                'uuid'    => $this->affectedHost->getUniqueId()->toString(),
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

    protected function getTitle()
    {
        return $this->title ?: $this->translate('Servicename');
    }

    protected function renderTitleColumns()
    {
        if (! $this->host || ! $this->affectedHost) {
            return Html::tag('th', $this->getTitle());
        }

        if ($this->readonly) {
            $link = $this->createFakeRemoveLinkForReadonlyView();
        } elseif ($this->affectedHost->get('id') !== $this->host->get('id')) {
            $link = $this->linkToHost($this->host);
        } else {
            $link = $this->createRemoveLinkForm();
        }

        return $this::th([$this->getTitle(), $link]);
    }

    /**
     * @return \Zend_Db_Select
     * @throws \Zend_Db_Select_Exception
     */
    public function prepareQuery()
    {
        $connection = $this->connection();
        assert($connection instanceof Db);
        $builder = new ServiceSetQueryBuilder($connection, $this->branchUuid);
        return $builder->selectServicesForSet($this->set)->limit(100);
    }

    protected function createFakeRemoveLinkForReadonlyView()
    {
        return Html::tag('span', [
            'class' => 'icon-paste',
            'style' => 'float: right; font-weight: normal',
        ], $this->host->getObjectName());
    }

    protected function linkToHost(IcingaHost $host)
    {
        $hostname = $host->getObjectName();
        return Link::create($hostname, 'director/host/services', ['name' => $hostname], [
            'class' => 'icon-paste',
            'style' => 'float: right; font-weight: normal',
            'data-base-target' => '_next',
            'title' => sprintf(
                $this->translate('This set has been inherited from %s'),
                $hostname
            )
        ]);
    }

    protected function createRemoveLinkForm()
    {
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
            $query = $db->select()->from(['ss' => 'icinga_service_set'], 'ss.id')
                ->join(['ssih' => 'icinga_service_set_inheritance'], 'ssih.service_set_id = ss.id', [])
                ->where('ssih.parent_service_set_id = ?', $this->set->get('id'))
                ->where('ss.host_id = ?', $this->host->get('id'));
            IcingaServiceSet::loadWithAutoIncId(
                $db->fetchOne($query),
                $conn
            )->delete();
        });
        $deleteLink->handleRequest();
        return $deleteLink;
    }
}
