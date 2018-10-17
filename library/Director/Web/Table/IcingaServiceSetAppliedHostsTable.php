<?php

namespace Icinga\Module\Director\Web\Table;

use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\db\ServiceSetHostLoader;
use Icinga\Data\DataArray\ArrayDatasource;
use Icinga\Module\Director\CustomVariable\CustomVariableDictionary;
use Icinga\Module\Director\Objects\IcingaHost;
use dipl\Html\Link;
use dipl\Web\Table\SimpleQueryBasedTable;

class IcingaServiceSetAppliedHostsTable extends SimpleQueryBasedTable
{
    protected $title;

    /** @var CustomVariableDictionary */
    protected $cv;

    protected $set;

    protected $searchColumns = [
        'service',
    ];

    /**
     * @return static
     */
    public static function load(IcingaServiceSet $set)
    {
        $table = (new static())->setServiceSet($set);
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

    public function setServiceSet($set)
    {
        $this->set = $set;
        return $this;
    }

    public function renderRow($row)
    {
        return $this::row([
            Link::create(
                $row->object_name,
                'director/host',
                ['name' => $row->object_name]
            )
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->title ?: $this->translate('Hosts by Apply Filter'),
        ];
    }

    public function prepareQuery()
    {
        $appliedHosts = ServiceSetHostLoader::fetchForServiceSet($this->set);

        return (new ArrayDatasource($appliedHosts))->select();
    }
}
