<?php

namespace Icinga\Module\Director\Web\Table;

use dipl\Html\Html;
use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Director\CustomVariable\CustomVariableDictionary;
use Icinga\Module\Director\Objects\IcingaHost;
use dipl\Html\Link;
use dipl\Web\Table\SimpleQueryBasedTable;

class IcingaHostAppliedForServiceTable extends SimpleQueryBasedTable
{
    protected $title;

    protected $host;

    /** @var CustomVariableDictionary */
    protected $cv;

    protected $searchColumns = [
        'service',
    ];

    /** @var bool */
    protected $readonly = false;

    /** @var string|null */
    protected $highlightedService;

    /**
     * @param IcingaHost $host
     * @param CustomVariableDictionary $dict
     * @return static
     */
    public static function load(IcingaHost $host, CustomVariableDictionary $dict)
    {
        $table = (new static())->setHost($host)->setDictionary($dict);
        $table->getAttributes()->set('data-base-target', '_self');
        return $table;
    }

    public function setDictionary(CustomVariableDictionary $dict)
    {
        $this->cv = $dict;
        return $this;
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

    public function renderRow($row)
    {
        if ($this->readonly) {
            if ($this->highlightedService === $row->service) {
                $link = Html::tag('span', ['class' => 'icon-right-big'], $row->service);
            } else {
                $link = $row->service;
            }
        } else {
            $link = Link::create($row->service, 'director/host/appliedservice', [
                'name'    => $this->host->object_name,
                'service' => $row->service,
            ]);
        }

        return $this::row([$link]);
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->title ?: $this->translate('Service name'),
        ];
    }

    public function prepareQuery()
    {
        $data = [];
        foreach ($this->cv->getValue() as $key => $var) {
            $data[] = (object) array(
                'service' => $key,
            );
        }

        return (new ArrayDatasource($data))->select();
    }
}
