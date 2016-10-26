<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Data\Filter\Filter;
use Icinga\Exception\IcingaException;
use Icinga\Module\Director\IcingaConfig\AssignRenderer;
use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaServiceTable extends QuickTable
{
    protected $searchColumns = array(
        'service',
    );

    public function getColumns()
    {
        return array(
            'id' => 's.id',
            'service' => 's.object_name',
            'object_type' => 's.object_type',
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
                        array('class' => 'icon-plus')
                    );
            }

        } else {
            $htm .= '. Related apply rules: <table class="apply-rules">';
            foreach ($extra as $service) {
                $href = $v->url('director/service', array('id' => $service->id));
                $htm .= "<tr href=\"$href\">";

                try {
                    $prettyFilter = AssignRenderer::forFilter(
                        Filter::fromQueryString($service->assign_filter)
                    )->renderAssign();
                }
                catch (IcingaException $e) {
                    // ignore errors in filter rendering
                    $prettyFilter = 'Error in Filter rendering: ' . $e->getMessage();
                }

                $htm .= "<td><a href=\"$href\">" . $service->object_name . '</a></td>';
                $htm .= '<td>' . $prettyFilter . '</td>';
                $htm .= '<tr>';
            }
            $htm .= '</table>';
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
        $db = $this->connection()->getConnection();
        $query = $db->select()->from(
            array('s' => 'icinga_service'),
            array(
                'id'          => 's.id',
                'object_name' => 's.object_name',
                'assign_filter' => 's.assign_filter',
            )
        )->join(
            array('i' => 'icinga_service_inheritance'),
            'i.service_id = s.id',
            array()
        )->where('i.parent_service_id = ?', $id)
        ->where('s.object_type = ?', 'apply');

        return $db->fetchAll($query);
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
