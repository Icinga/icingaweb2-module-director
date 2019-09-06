<?php

namespace Icinga\Module\Director\Web\Tabs;

use dipl\Translation\TranslationHelper;
use dipl\Web\Widget\Tabs;

class DataTabs extends Tabs
{
    use TranslationHelper;

    public function __construct()
    {
        // We are not a BaseElement, not yet
        $this->assemble();
    }

    protected function assemble()
    {
        $this->add('datafield', [
            'label' => $this->translate('Data fields'),
            'url'   => 'director/data/fields'
        ])->add('datalist', [
            'label' => $this->translate('Data lists'),
            'url'   => 'director/data/lists'
        ])->add('customvars', [
            'label' => $this->translate('Custom Variables'),
            'url'   => 'director/data/vars'
        ]);
    }
}
