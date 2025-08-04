<?php

namespace Icinga\Module\Director\Web\Form;

use ipl\I18n\Translation;
use gipfl\Web\InlineForm;

class ClickHereForm extends InlineForm
{
    use Translation;

    protected $hasBeenClicked = false;

    protected function assemble()
    {
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('here'),
            'class' => 'link-button'
        ]);
    }

    public function hasBeenClicked()
    {
        return $this->hasBeenClicked;
    }

    public function onSuccess()
    {
        $this->hasBeenClicked = true;
    }
}
