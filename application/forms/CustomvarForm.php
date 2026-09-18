<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\QuickForm;

class CustomvarForm extends QuickForm
{
    protected $submitLabel = false;

    public function setup()
    {
        $this->removeCsrfToken();
        $this->removeElement(self::ID);
        $this->addElement('text', 'varname', array(
            'label'       => $this->translate('Variable name'),
            'required'    => true,
        ));

        $this->addElement('text', 'varvalue', array(
            'label' => $this->translate('Value'),
        ));

        // $this->addHidden('format', 'string'); // expression, json?
    }
}
