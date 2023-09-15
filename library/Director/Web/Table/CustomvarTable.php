<?php

namespace Icinga\Module\Director\Web\Table;

use ipl\Html\Html;
use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Zend_Db_Adapter_Abstract as ZfDbAdapter;
use Zend_Db_Select as ZfDbSelect;

class CustomvarTable extends ZfQueryBasedTable
{
    protected $searchColumns = array(
        'varname',
    );

    public function renderRow($row)
    {
        $tr = $this::row([
            new Link(
                $row->varname,
                'director/customvar/variants',
                ['name' => $row->varname]
            )
        ]);

        foreach ($this->getObjectTypes() as $type) {
            $tr->add($this::td(Html::tag('nobr', null, sprintf(
                $this->translate('%d / %d'),
                $row->{"cnt_$type"},
                $row->{"distinct_$type"}
            ))));
        }

        return $tr;
    }

    public function getColumnsToBeRendered()
    {
        return array(
            $this->translate('Variable name'),
            $this->translate('Distinct Commands'),
            $this->translate('Hosts'),
            $this->translate('Services'),
            $this->translate('Service Sets'),
            $this->translate('Notifications'),
            $this->translate('Users'),
        );
    }

    protected function getObjectTypes()
    {
        return ['command', 'host', 'service', 'service_set', 'notification', 'user'];
    }

    public function prepareQuery()
    {
        $db = $this->db();
        $varsColumns = ['varname' => 'v.varname'];
        $varsTypes = $this->getObjectTypes();
        foreach ($varsTypes as $type) {
            $varsColumns["cnt_$type"] = '(0)';
            $varsColumns["distinct_$type"] = '(0)';
        }
        $varsQueries = [];
        foreach ($varsTypes as $type) {
            $varsQueries[] = $this->makeVarSub($type, $varsColumns, $db);
        }

        $union = $db->select()->union($varsQueries, ZfDbSelect::SQL_UNION_ALL);

        $columns = ['varname' => 'u.varname'];
        foreach ($varsTypes as $column) {
            $columns["cnt_$column"] = "SUM(u.cnt_$column)";
            $columns["distinct_$column"] = "SUM(u.distinct_$column)";
        }
        return $db->select()->from(
            array('u' => $union),
            $columns
        )->group('u.varname')->order('u.varname ASC')->limit(100);
    }

    /**
     * @param string $type
     * @param array $columns
     * @param ZfDbAdapter $db
     * @return ZfDbSelect
     */
    protected function makeVarSub($type, array $columns, ZfDbAdapter $db)
    {
        $columns["cnt_$type"] = 'COUNT(*)';
        $columns["distinct_$type"] = 'COUNT(DISTINCT varvalue)';
        return $db->select()->from(
            ['v' => "icinga_{$type}_var"],
            $columns
        )->join(
            ['o' => "icinga_{$type}"],
            "o.id = v.{$type}_id",
            []
        )->where('o.object_type != ?', 'external_object')->group('varname');
    }
}
