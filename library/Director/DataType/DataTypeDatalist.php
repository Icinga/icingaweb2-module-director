<?php

namespace Icinga\Module\Director\DataType;

use Icinga\Module\Director\Acl;
use Icinga\Module\Director\Hook\DataTypeHook;
use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

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

    protected function getEntries(QuickForm $form)
    {
        /** @var DirectorObjectForm $form */
        $db = $form->getDb()->getDbAdapter();

        $roles = array_map('json_encode', Acl::instance()->listRoleNames());
        $select = $db->select()
            ->from('director_datalist_entry', array('entry_name', 'entry_value'))
            ->where('list_id = ?', $this->getSetting('datalist_id'))
            ->order('entry_value ASC');

        if (empty($roles)) {
            $select->where('allowed_roles IS NULL');
        } else {
            $select->where('(allowed_roles IS NULL OR allowed_roles IN (?))', $roles);
        }

        return $db->fetchPairs($select);
    }

    public static function addSettingsFormFields(QuickForm $form)
    {
        /** @var DirectorObjectForm $form */
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
