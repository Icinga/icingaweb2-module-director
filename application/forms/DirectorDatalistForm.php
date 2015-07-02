<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class DirectorDatalistForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addElement('text', 'list_name', array(
            'label' => $this->translate('List name')
        ));
    }
}
