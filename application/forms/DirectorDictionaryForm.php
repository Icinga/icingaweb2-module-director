<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class DirectorDictionaryForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'dictionary_name', array(
            'label'       => $this->translate('Name'),
            'required'    => true,
        ));
        $this->addSimpleDisplayGroup(array('dictionary_name'), 'dictionary', array(
            'legend' => $this->translate('Data dictionary')
        ));

        $this->setButtons();
    }

    public function onSuccess()
    {
        $this->object()->owner = self::username();
        parent::onSuccess();
    }
}
