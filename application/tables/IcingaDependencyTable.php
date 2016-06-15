<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\IcingaObjectTable;

class IcingaDependencyTable extends IcingaObjectTable
{
    protected $searchColumns = array( //TODO, check on this
        'child_host',
    );

    public function getColumns()
    {
        return array(
            'id'                    => 'n.id',
            'object_type'           => 'n.object_type',
            'dependency'            => 'n.object_name',
        );
    }

    protected function listTableClasses()
    {
        return array_merge(array('assignment-table'), parent::listTableClasses());
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/dependency', array('id' => $row->id));
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'dependency' => $view->translate('Dependency'),
        );
    }

    protected function renderRow($row)
    {
        $v = $this->view();
        $extra = $this->appliedOnes($row->id);
        $htm = "  <tr" . $this->getRowClassesString($row) . ">\n";
        $htm .= '<td>' . $v->qlink($row->dependency, $this->getActionUrl($row));
        if (empty($extra)) {
            $htm .= ' ' . $v->qlink(
                'Create apply-rule',
                'director/dependency/add',
                array('apply' => $row->dependency),
                array('class'    => 'icon-plus')
            );

        } else {
            $htm .= '. Related apply rules: <ul class="apply-rules">';
            foreach ($extra as $id => $dependency) {
                $htm .= '<li>'
                    . $v->qlink($dependency, 'director/dependency', array('id' => $id))
                    . '</li>';
            }
            $htm .= '</ul>';
            $htm .= $v->qlink(
                'Add more',
                'director/dependency/add',
                array('apply' => $row->dependency),
                array('class' => 'icon-plus')
            );
        }
        $htm .= '</td>';
        return $htm . "  </tr>\n";
    }

    protected function appliedOnes($id)
    {
        if ($this->connection()->isPgsql()) {
            $nameCol = "s.object_name || COALESCE(': ' || ARRAY_TO_STRING(ARRAY_AGG("
                . "a.assign_type || ' where ' || a.filter_string"
                . " ORDER BY a.assign_type, a.filter_string), ', '), '')";
        } else {
            $nameCol = "s.object_name || COALESCE(': ' || GROUP_CONCAT("
                . "a.assign_type || ' where ' || a.filter_string"
                . " ORDER BY a.assign_type, a.filter_string SEPARATOR ', '"
                . "), '')";
        }

        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('s' => 'icinga_dependency'),
            array(
                'id'         => 's.id',
                'objectname' => $nameCol,
            )
        )->join(
            array('i' => 'icinga_dependency_inheritance'),
            'i.dependency_id = s.id',
            array()
        )->where('i.parent_dependency_id = ?', $id)
         ->where('s.object_type = ?', 'apply');

        $query->joinLeft(
            array('a' => 'icinga_dependency_assignment'),
            'a.dependency_id = s.id',
            array()
        )->group('s.id');

        return $db->fetchPairs($query);
    }

    public function getUnfilteredQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('n' => 'icinga_dependency'),
            array()
        );

        return $query;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}
