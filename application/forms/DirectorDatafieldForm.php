<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\QuickForm;

class DirectorDatafieldForm extends QuickForm
{
    protected $db;
    protected $successUrl = 'director/list/datafield';

    public function setup()
    {
        $this->addElement('text', 'varname', array(
            'required' => true,
            'label' => $this->translate('Field name'),
            'description' => $this->translate('The unique name of the field')
        ));

        $this->addElement('text', 'caption', array(
            'label' => $this->translate('Caption'),
            'description' => $this->translate('The caption which should be displayed')
        ));

        $this->addElement('textarea', 'description', array(
            'label' => $this->translate('Description'),
            'description' => $this->translate('A description about the field')
        ));

        $this->addElement('text', 'datatype', array(
            'label' => $this->translate('Data type'),
            'description' => $this->translate('Field type')
        ));

        $this->addElement('text', 'format', array(
            'label' => $this->translate('Format'),
            'description' => $this->translate('Field format (string, json, expression)')
        ));
    }

    public function onSuccess()
    {
        $values = $this->getValues();

        $this->db->insert(
            'director_datafield',
            array(
                'varname'       => $values['varname'],
                'caption'       => $values['caption'],
                'description'   => $values['description'],
                'datatype'      => $values['datatype'],
                'format'        => $values['format'],
            )
        );

        parent::onSuccess('Ding dong');
    }

    public function setDb($db)
    {
        $this->db = $db;
    }
}
