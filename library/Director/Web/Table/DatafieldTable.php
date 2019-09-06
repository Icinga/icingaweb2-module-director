<?php

namespace Icinga\Module\Director\Web\Table;

use dipl\Html\Link;
use dipl\Web\Table\ZfQueryBasedTable;
use Zend_Db_Adapter_Abstract as ZfDbAdapter;
use Zend_Db_Select as ZfDbSelect;

class DatafieldTable extends ZfQueryBasedTable
{
    protected $searchColumns = array(
        'df.varname',
        'df.caption',
    );

    public function getColumns()
    {
        return array(
            'id'              => 'df.id',
            'varname'         => 'df.varname',
            'caption'         => 'df.caption',
            'description'     => 'df.description',
            'datatype'        => 'df.datatype',
            'assigned_fields' => 'SUM(used_fields.cnt)',
            'assigned_vars'   => 'SUM(used_vars.cnt)',
        );
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
            $this::td($row->assigned_fields),
            $this::td($row->assigned_vars)
        ]);
    }

    public function getColumnsToBeRendered()
    {
        return array(
            $this->translate('Label'),
            $this->translate('Field name'),
            $this->translate('# Used'),
            $this->translate('# Vars'),
        );
    }

    public function prepareQuery()
    {
        $db = $this->db();
        $fieldTypes = array('command', 'host', 'notification', 'service', 'user');
        $varsTypes  = array('command', 'host', 'notification', 'service', 'service_set', 'user');

        $fieldsQueries = array();
        foreach ($fieldTypes as $type) {
            $fieldsQueries[] = $this->makeDatafieldSub($type, $db);
        }

        $varsQueries = array();
        foreach ($varsTypes as $type) {
            $varsQueries[] = $this->makeVarSub($type, $db);
        }

        return $db->select()->from(
            array('df' => 'director_datafield'),
            $this->getColumns()
        )->joinLeft(
            array('used_fields' => $db->select()->union($fieldsQueries, ZfDbSelect::SQL_UNION_ALL)),
            'used_fields.datafield_id = df.id',
            array()
        )->joinLeft(
            array('used_vars' => $db->select()->union($varsQueries, ZfDbSelect::SQL_UNION_ALL)),
            'used_vars.varname = df.varname',
            array()
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
        return $db->select()
            ->from(
                sprintf('icinga_%s_field', $type),
                array(
                    'cnt' => 'COUNT(*)',
                    'datafield_id'
                )
            )->group('datafield_id');
    }

    /**
     * @param $type
     * @param ZfDbAdapter $db
     *
     * @return ZfDbSelect
     */
    protected function makeVarSub($type, ZfDbAdapter $db)
    {
        return $db->select()
            ->from(
                sprintf('icinga_%s_var', $type),
                array(
                    'cnt' => 'COUNT(*)',
                    'varname'
                )
            )->group('varname');
    }
}
