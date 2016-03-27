<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaServiceTable extends QuickTable
{
    protected $searchColumns = array(
        'service',
    );

    public function getColumns()
    {
        return array(
            'id'               => 's.id',
            'service'          => 's.object_name',
            'object_type'      => 's.object_type',
            'check_command_id' => 's.check_command_id',
        );
    }

    protected function listTableClasses()
    {
        return array_merge(array('assignment-table'), parent::listTableClasses());
    }

    protected function getActionUrl($row)
    {
        // TODO: Remove once we got a separate apply table
        if ($row->object_type === 'apply') {
            $params['id'] = $row->id;
        } else {
            $params = array('name' => $row->service);

        }

        return $this->url('director/service', $params);
    }

    protected function renderRow($row)
    {
        $v = $this->view();
        $extra = $this->appliedOnes($row->id);
        $htm = "  <tr" . $this->getRowClassesString($row) . ">\n";
        $htm .= '<td>' . $v->qlink($row->service, $this->getActionUrl($row));
        if (empty($extra)) {
            if ($row->check_command_id) {
                $htm .= ' ' . $v->qlink(
                    'Create apply-rule',
                    'director/service/add',
                    array('apply' => $row->service),
                    array('class'    => 'icon-plus')
                );
            }

        } else {
            $htm .= '. Related apply rules: <ul class="apply-rules">';
            foreach ($extra as $id => $service) {
                $htm .= '<li>'
                    . $v->qlink($service, 'director/service', array('id' => $id))
                    . '</li>';
            }
            $htm .= '</ul>';
            $htm .= $v->qlink(
                'Add more',
                'director/service/add',
                array('apply' => $row->service),
                array('class' => 'icon-plus')
            );
        }
        $htm .= '</td>';
        return $htm . "  </tr>\n";
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'service' => $view->translate('Servicename'),
        );
    }

    public function getUnfilteredQuery()
    {
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('s' => 'icinga_service'),
            array()
        );

        return $query;
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
            array('s' => 'icinga_service'),
            array(
                'id'         => 's.id',
                'objectname' => $nameCol,
            )
        )->join(
            array('i' => 'icinga_service_inheritance'),
            'i.service_id = s.id',
            array()
        )->where('i.parent_service_id = ?', $id)
         ->where('s.object_type = ?', 'apply');

        $query->joinLeft(
            array('a' => 'icinga_service_assignment'),
            'a.service_id = s.id',
            array()
        )->group('s.id');

        return $db->fetchPairs($query);
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery()->where(
            's.object_type IN (?)',
            array('template')
        )->order('CASE WHEN s.check_command_id IS NULL THEN 1 ELSE 0 END')
         ->order('s.object_name');
    }
}
