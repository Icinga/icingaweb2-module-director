<?php

namespace Icinga\Module\Director\Web\Tabs;

use ipl\I18n\Translation;
use gipfl\IcingaWeb2\Widget\Tabs;

class ImportTabs extends Tabs
{
    use Translation;

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
