<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Application\Config;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\DirectorDatalist;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class DirectorDatalistEntryForm extends DirectorObjectForm
{
    /** @var  DirectorDatalist */
    protected $datalist;

    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $this->addElement('text', 'entry_name', [
            'label'       => $this->translate('Key'),
            'required'    => true,
            'description' => $this->translate(
                'Will be stored as a custom variable value when this entry'
                . ' is chosen from the list'
            )
        ]);

        $this->addElement('text', 'entry_value', [
            'label'       => $this->translate('Label'),
            'required'    => true,
            'description' => $this->translate(
                'This will be the visible caption for this entry'
            )
        ]);

        $rolesConfig = Config::app('roles', true);
        $roles = [];
        foreach ($rolesConfig as $name => $role) {
            $roles[$name] = $name;
        }

        $this->addElement('extensibleSet', 'allowed_roles', [
            'label'        => $this->translate('Allowed roles'),
            'required'     => false,
            'multiOptions' => $roles,
            'description'  => $this->translate(
                'Allow to use this entry only to users with one of these Icinga Web 2 roles'
            )
        ]);

        $this->addHidden('list_id', $this->datalist->get('id'));
        $this->addHidden('format', 'string');
        if (!$this->isNew()) {
            $this->addHidden('entry_name', $this->object->get('entry_name'));
        }

        $this->addSimpleDisplayGroup(['entry_name', 'entry_value', 'allowed_roles'], 'entry', [
            'legend' => $this->isNew()
                ? $this->translate('Add data list entry')
                : $this->translate('Modify data list entry')
        ]);

        $this->setButtons();
    }

    /**
     * @param DirectorDatalist $list
     * @return $this
     */
    public function setList(DirectorDatalist $list)
    {
        $this->datalist = $list;
        /** @var Db $db */
        $db = $list->getConnection();
        $this->setDb($db);

        return $this;
    }
}
