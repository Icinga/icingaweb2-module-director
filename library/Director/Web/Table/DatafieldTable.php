<?php

namespace Icinga\Module\Director\Web\Table;

use gipfl\IcingaWeb2\Link;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use Zend_Db_Adapter_Abstract as ZfDbAdapter;
use Zend_Db_Select as ZfDbSelect;

class DatafieldTable extends ZfQueryBasedTable
{
    protected $searchColumns = [
        'df.varname',
        'df.caption',
    ];

    public function getColumns()
    {
        return [
            'id'              => 'df.id',
            'varname'         => 'df.varname',
            'caption'         => 'df.caption',
            'description'     => 'df.description',
            'datatype'        => 'df.datatype',
            'category'        => 'dfc.category_name',
            'assigned_fields' => 'SUM(used_fields.cnt)',
            'assigned_vars'   => 'SUM(used_vars.cnt)',
        ];
    }

    public function renderRow($row)
    {
        return $this::tr([
            $this::td(Link::create(
                $row->caption,
                'director/datafield/edit',
                ['id' => $row->id]
            )),
            $this::td($row->varname),
            $this::td($row->category),
            $this::td($row->assigned_fields),
            $this::td($row->assigned_vars)
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return [
            $this->translate('Label'),
            $this->translate('Field name'),
            $this->translate('Category'),
            $this->translate('# Used'),
            $this->translate('# Vars'),
        ];
    }

    public function prepareQuery()
    {
        $db = $this->db();
        $fieldTypes = ['command', 'host', 'notification', 'service', 'user'];
        $varsTypes  = ['command', 'host', 'notification', 'service', 'service_set', 'user'];

        $fieldsQueries = [];
        foreach ($fieldTypes as $type) {
            $fieldsQueries[] = $this->makeDatafieldSub($type, $db);
        }

        $varsQueries = [];
        foreach ($varsTypes as $type) {
            $varsQueries[] = $this->makeVarSub($type, $db);
        }

        return $db->select()->from(
            ['df' => 'director_datafield'],
            $this->getColumns()
        )->joinLeft(
            ['dfc' => 'director_datafield_category'],
            'df.category_id = dfc.id',
            []
        )->joinLeft(
            ['used_fields' => $db->select()->union($fieldsQueries, ZfDbSelect::SQL_UNION_ALL)],
            'used_fields.datafield_id = df.id',
            []
        )->joinLeft(
            ['used_vars' => $db->select()->union($varsQueries, ZfDbSelect::SQL_UNION_ALL)],
            'used_vars.varname = df.varname',
            []
        )->group('df.id')->group('df.varname')->order('caption ASC');
    }

    /**
     * @param $type
     * @param ZfDbAdapter $db
     *
     * @return ZfDbSelect
     */
    protected function makeDatafieldSub($type, ZfDbAdapter $db)
    {
        return $db->select()->from("icinga_${type}_field", [
            'cnt' => 'COUNT(*)',
            'datafield_id'
        ])->group('datafield_id');
    }

    /**
     * @param $type
     * @param ZfDbAdapter $db
     *
     * @return ZfDbSelect
     */
    protected function makeVarSub($type, ZfDbAdapter $db)
    {
        return $db->select()->from("icinga_${type}_var", [
            'cnt' => 'COUNT(*)',
            'varname'
        ])->group('varname');
    }
}
