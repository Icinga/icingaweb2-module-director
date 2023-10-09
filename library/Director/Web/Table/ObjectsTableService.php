<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaHost;
use ipl\Html\Html;
use gipfl\IcingaWeb2\Table\Extension\MultiSelect;
use gipfl\IcingaWeb2\Link;
use Ramsey\Uuid\Uuid;

class ObjectsTableService extends ObjectsTable
{
    use MultiSelect;

    /** @var IcingaHost */
    protected $host;

    protected $type = 'service';

    protected $title;

    /** @var IcingaHost */
    protected $inheritedBy;

    /** @var bool */
    protected $readonly = false;

    /** @var string|null */
    protected $highlightedService;

    protected $columns = [
        'object_name'      => 'o.object_name',
        'disabled'         => 'o.disabled',
        'host'             => 'h.object_name',
        'host_id'          => 'h.id',
        'host_object_type' => 'h.object_type',
        'host_disabled'    => 'h.disabled',
        'id'               => 'o.id',
        'uuid'             => 'o.uuid',
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

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        $this->getAttributes()->set('data-base-target', '_self');
        return $this;
    }

    public function setInheritedBy(IcingaHost $host)
    {
        $this->inheritedBy = $host;
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

    public function getColumnsToBeRendered()
    {
        if ($this->title) {
            return [$this->title];
        }
        if ($this->host) {
            return [$this->translate('Servicename')];
        }
        return [
            'host'        => $this->translate('Host'),
            'object_name' => $this->translate('Service Name'),
        ];
    }

    public function renderRow($row)
    {
        $caption = $row->host === null
            ? Html::tag('span', ['class' => 'error'], '- none -')
            : $row->host;

        $hostField = static::td($caption);
        if ($row->host === null) {
            $hostField->getAttributes()->add('class', 'error');
        }
        if ($this->host) {
            $tr = static::tr([
                static::td($this->getServiceLink($row))
            ]);
        } else {
            $tr = static::tr([
                $hostField,
                static::td($this->getServiceLink($row))
            ]);
        }

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

    protected function getInheritedServiceLink($row, $target)
    {
        $params = [
            'name'          => $target->object_name,
            'service'       => $row->object_name,
            'inheritedFrom' => $row->host,
        ];

        return Link::create(
            $row->object_name,
            'director/host/inheritedservice',
            $params
        );
    }

    protected function getServiceLink($row)
    {
        if ($this->readonly) {
            if ($this->highlightedService === $row->object_name) {
                return Html::tag('span', ['class' => 'icon-right-big'], $row->object_name);
            } else {
                return $row->object_name;
            }
        }

        $params = [
            'uuid' => Uuid::fromBytes(DbUtil::binaryResult($row->uuid))->toString(),
        ];
        if ($row->host !== null) {
            $params['host'] = $row->host;
        }
        if ($target = $this->inheritedBy) {
            return $this->getInheritedServiceLink($row, $target);
        }

        return Link::create(
            $row->object_name,
            'director/service/edit',
            $params
        );
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
                ->group(['o.id', 'h.id','hsb.service_id', 'hsb.host_id'])
                ->order('o.object_name')->order('h.object_name');

            if ($this->branchUuid) {
                $subQuery->where('bo.service_set IS NULL')
                    ->group(['bo.uuid', 'bo.branch_uuid']);
            }

            if ($this->host) {
                if ($this->branchUuid) {
                    $subQuery->where('COALESCE(h.object_name, bo.host) = ?', $this->host->getObjectName());
                } else {
                    $subQuery->where('h.id = ?', $this->host->get('id'));
                }
            }
        }

        return $query;
    }
}
