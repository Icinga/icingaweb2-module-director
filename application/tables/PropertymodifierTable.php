<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class PropertymodifierTable extends QuickTable
{
    public function getColumns()
    {
        return array(
            'id'                => 'm.id',
            'source_id'         => 'm.source_id',
            'source_name'       => 's.source_name',
            'property'          => 'CASE WHEN m.target_property IS NULL'
                                 . ' THEN m.property_name'
                                 . " ELSE m.target_property || ' <- ' || m.property_name END",
            'provider_class'    => 'm.provider_class',
            'priority'          => 'm.priority',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url(
            'director/importsource/editmodifier',
            array(
                'id'        => $row->id,
                'source_id' => $row->source_id,
            )
        );
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'property' => $view->translate('Property'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('s' => 'import_source'),
            array()
        )->join(
            array('m' => 'import_row_modifier'),
            's.id = m.source_id',
            array()
        )->order('property')
        ->order('priority');

        return $query;
    }
}
