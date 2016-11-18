<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\IcingaObjectTable;

class IcingaDependencyTable extends IcingaObjectTable
{
    protected $searchColumns = array(
        'dependency',
    );

    public function getColumns()
    {
        return array(
            'id'                    => 'd.id',
            'object_type'           => 'd.object_type',
            'dependency'            => 'd.object_name',
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
                array('apply' => $row->dependency, 'type' => 'apply'),
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
        $db = $this->db();
        $query = $db->select()->from(
            array('s' => 'icinga_dependency'),
            array(
                'id'         => 's.id',
                'objectname' => 's.object_name',
            )
        )->join(
            array('i' => 'icinga_dependency_inheritance'),
            'i.dependency_id = s.id',
            array()
        )->where('i.parent_dependency_id = ?', $id)
         ->where('s.object_type = ?', 'apply');


        return $db->fetchPairs($query);
    }

    public function getUnfilteredQuery()
    {
        return $this->db()->select()->from(
            array('d' => 'icinga_dependency'),
            array()
        );
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->order('d.object_name');
    }
}
