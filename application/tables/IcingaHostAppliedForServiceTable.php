<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Director\CustomVariable\CustomVariableDictionary;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Table\QuickTable;
use ipl\Html\ValidHtml;

class IcingaHostAppliedForServiceTable extends QuickTable implements ValidHtml
{
    protected $title;

    protected $host;

    protected $searchColumns = array(
        'service',
    );

    public function setDictionary(CustomVariableDictionary $dict)
    {
        $data = array();

        foreach ($dict->getValue() as $key => $var) {
            $data[] = (object) array(
                'service' => $key,
            );
        }

        $this->setConnection(new ArrayDatasource($data));
        return $this;
    }

    public function count()
    {
        $query = clone($this->getBaseQuery());
        $this->applyFiltersToQuery($query);
        return $query->count();
    }

    public function fetchData()
    {
        $query = $this->getBaseQuery()->columns($this->getColumns());

        if ($this->hasLimit() || $this->hasOffset()) {
            $query->limit($this->getLimit(), $this->getOffset());
        }

        $this->applyFiltersToQuery($query);

        return $query->fetchAll();
    }

    public function getColumns()
    {
        return array(
            'service' => 'service'
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
        return $this;
    }

    protected function getActionUrl($row)
    {
        $params = array(
            'name'    => $this->host->object_name,
            'service' => $row->service,
        );

        return $this->url('director/host/appliedservice', $params);
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'service' => $this->title ?: $view->translate('Servicename'),
        );
    }

    public function getBaseQuery()
    {
        return $this->db()->select();
    }
}
