<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Web\Table\QuickTable;

class DictionaryTable extends QuickTable
{
    protected $searchColumns = array(
        'dictionary_name',
    );

    public function getColumns()
    {
        return array(
            'id'              => 'l.id',
            'dictionary_name' => 'l.dictionary_name',
            'owner'           => 'l.owner',
        );
    }

    protected function getActionUrl($row)
    {
        return $this->url(
            'director/data/dictionaryfield',
            array('dictionary_id' => $row->id)
        );
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'dictionary_name' => $view->translate('Dictionary name'),
            'owner' => $view->translate('Owner'),
        );
    }

    public function getBaseQuery()
    {
        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('l' => 'director_dictionary'),
            array()
        )->order('dictionary_name ASC');

        return $query;
    }
}
