<?php

namespace Icinga\Module\Director\Web\Form;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\InlineForm;

class ClickHereForm extends InlineForm
{
    use TranslationHelper;

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
