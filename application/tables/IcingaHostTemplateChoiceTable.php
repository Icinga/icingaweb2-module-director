<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class IcingaHostTemplateChoiceTable extends QuickTable
{
    protected $searchColumns = [
        'name',
    ];

    public function getTitles()
    {
        $view = $this->view();
        return [
            'name'      => $view->translate('Name'),
            'templates' => $view->translate('Choices'),
        ];
    }

    public function getColumns()
    {
        return [
            'id'   => 'hc.id',
            'name' => 'hc.object_name',
            'templates' => 'GROUP_CONCAT(t.object_name)'
        ];
    }

    protected function getActionUrl($row)
    {
        return $this->url('director/hosttemplatechoice', array('name' => $row->name));
    }

    protected function getUnfilteredQuery()
    {
        $query = $this->db()->select()->from(
            ['hc' => 'icinga_host_template_choice'],
            []
        )->joinLeft(
            ['t' => 'icinga_host'],
            't.template_choice_id = hc.id',
            []
        )->order('hc.object_name')->group('hc.id');

        return $query;
    }

    public function getBaseQuery()
    {
        return $this->getUnfilteredQuery();
    }
}
