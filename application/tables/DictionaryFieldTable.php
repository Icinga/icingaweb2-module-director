<?php

namespace Icinga\Module\Director\Tables;

use Icinga\Module\Director\Objects\DirectorDictionary;
use Icinga\Module\Director\Web\Table\QuickTable;
use Icinga\Application\Hook;

class DictionaryFieldTable extends QuickTable
{
    protected $dictionary;

    protected $searchColumns = array(
        'varname',
    );

    public function setDictionary(DirectorDictionary $dictionary)
    {
        $this->dictionary = $dictionary;
        return $this;
    }

    public function getDictionary()
    {
        return $this->dictionary;
    }

    public function getColumns()
    {
        return array();
    }

    protected function getActionUrl($row)
    {
        return $this->url(
            'director/data/dictionaryfield/edit',
            array(
                'id' => $row->id,
                'dictionary_id' => $row->dictionary_id
            )
        );
    }

    public function getTitles()
    {
        $view = $this->view();
        return array(
            'varname' => $view->translate('Variable Name'),
            'caption' => $view->translate('Caption'),
            'datatype' => $view->translate('Data Type')
        );
    }

    public function getBaseQuery()
    {
        $hooks = Hook::all('Director\\DataType');
        $expression = "CASE ";
        foreach ($hooks as $hook) {
            $expression .= sprintf("WHEN l.datatype = '%s' THEN '%s' ", str_replace('\\', '\\\\', get_class($hook)), $hook->getName());
        }
        $expression .= "ELSE l.datatype END";

        $db = $this->connection()->getConnection();

        $query = $db->select()->from(
            array('l' => 'director_dictionary_field'),
            array(
                'id',
                'dictionary_id',
                'varname',
                'caption',
                'datatype' => new \Zend_Db_Expr($expression)
            )
        )->where(
            'l.dictionary_id = ?',
            $this->getDictionary()->id
        )->order('varname ASC');

        return $query;
    }
}
