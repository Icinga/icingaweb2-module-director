<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\QuickForm;

class DataTypeDatalist extends DataTypeHook
{
    public function getFormElement($name, QuickForm $form)
    {
        $element = $form->createElement('select', $name, array(
            'multiOptions' => array(null => '- please choose -') +
                $this->getEntries($form),
        ));

        return $element;
    }

    protected function getEntries($form)
    {
        $db = $form->getDb()->getDbAdapter();

        $select = $db->select()
            ->from('director_datalist_entry', array('entry_name', 'entry_value'))
            ->where('list_id = ?', $this->settings['datalist_id'])
            ->order('entry_value ASC');

        return $db->fetchPairs($select);
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        $db = $form->getDb();

        $form->addElement('select', 'datalist_id', array(
            'label'    => 'List name',
            'required' => true,
            'multiOptions' => array(null => '- please choose -') +
                $db->enumDatalist(),
        ));
        return $form;
    }
}
