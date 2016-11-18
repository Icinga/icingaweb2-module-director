<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;
use Zend_Db_Adapter_Abstract as ZfDbAdapter;
use Zend_Db_Select as ZfDbSelect;

class DatafieldTable extends QuickTable
{
    protected $searchColumns = array(
        'varname',
        'caption',
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

    protected function getActionUrl($row)
    {
        return $this->url('director/datafield/edit', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'caption'         => $view->translate('Label'),
            'varname'         => $view->translate('Field name'),
            'assigned_fields' => $view->translate('# Used'),
            'assigned_vars'   => $view->translate('# Vars'),
        );
    }

    public function getBaseQuery()
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
            array()
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

    public function count()
    {
        $db = $this->db();
        return $db->fetchOne(
            $db->select()->from(
                array('sub' => $this->getBaseQuery()->columns($this->getColumns())),
                'COUNT(*)'
            )
        );
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
