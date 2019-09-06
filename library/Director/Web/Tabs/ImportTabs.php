<?php

namespace Icinga\Module\Director\Web\Tabs;

use dipl\Translation\TranslationHelper;
use dipl\Web\Widget\Tabs;

class ImportTabs extends Tabs
{
    use TranslationHelper;

    public function __construct()
    {
        $this->assemble();
    }

    protected function assemble()
    {
        $this->add('importsource', [
            'label' => $this->translate('Import source'),
            'url'   => 'director/importsources'
        ])->add('syncrule', [
            'label' => $this->translate('Sync rule'),
            'url'   => 'director/syncrules'
        ])->add('jobs', [
            'label' => $this->translate('Jobs'),
            'url'   => 'director/jobs'
        ]);
    }
}
