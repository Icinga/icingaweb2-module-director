<?php

namespace Icinga\Module\Director\Web\Tabs;

use ipl\I18n\Translation;
use gipfl\IcingaWeb2\Widget\Tabs;

class ImportsourceTabs extends Tabs
{
    use Translation;

    protected $id;

    public function __construct($id = null)
    {
        $this->id = $id;
        $this->assemble();
    }

    public function activateMainWithPostfix($postfix)
    {
        $mainTab = 'index';
        $tab = $this->get($mainTab);
        $tab->setLabel($tab->getLabel() . ": $postfix");
        $this->activate($mainTab);

        return $this;
    }

    protected function assemble()
    {
        if ($id = $this->id) {
            $params = ['id' => $id];
            $this->add('index', [
                'url'       => 'director/importsource',
                'urlParams' => $params,
                'label'     => $this->translate('Import source'),
            ])->add('modifier', [
                'url'       => 'director/importsource/modifier',
                'urlParams' => ['source_id' => $id],
                'label'     => $this->translate('Modifiers'),
            ])->add('history', [
                'url'       => 'director/importsource/history',
                'urlParams' => $params,
                'label'     => $this->translate('History'),
            ])->add('preview', [
                'url'       => 'director/importsource/preview',
                'urlParams' => $params,
                'label'     => $this->translate('Preview'),
            ]);
        } else {
            $this->add('add', [
                'url'   => 'director/importsource/add',
                'label' => $this->translate('New import source'),
            ])->activate('add');
        }
    }
}
