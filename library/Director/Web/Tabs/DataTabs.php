<?php

namespace Icinga\Module\Director\Web\Tabs;

use ipl\I18n\Translation;
use gipfl\IcingaWeb2\Widget\Tabs;

class DataTabs extends Tabs
{
    use Translation;

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
        ])->add('datafieldcategory', [
            'label' => $this->translate('Data field categories'),
            'url'   => 'director/data/fieldcategories'
        ])->add('datalist', [
            'label' => $this->translate('Data lists'),
            'url'   => 'director/data/lists'
        ])->add('customvars', [
            'label' => $this->translate('Custom Variables'),
            'url'   => 'director/data/vars'
        ]);
    }
}
