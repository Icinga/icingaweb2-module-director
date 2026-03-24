<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Tabs;

use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\Tabs;

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
