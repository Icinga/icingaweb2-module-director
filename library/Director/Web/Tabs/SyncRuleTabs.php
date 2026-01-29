<?php

namespace Icinga\Module\Director\Web\Tabs;

use Icinga\Module\Director\Objects\SyncRule;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Widget\Tabs;

class SyncRuleTabs extends Tabs
{
    use TranslationHelper;

    protected $rule;

    public function __construct(?SyncRule $rule = null)
    {
        $this->rule = $rule;
        // We are not a BaseElement, not yet
        $this->assemble();
    }

    protected function assemble()
    {
        if ($this->rule) {
            $id = $this->rule->get('id');
            $this->add('show', [
                'url'       => 'director/syncrule',
                'urlParams' => ['id' => $id],
                'label'     => $this->translate('Sync rule'),
            ])->add('preview', [
                'url'       => 'director/syncrule/preview',
                'urlParams' => ['id' => $id],
                'label'     => $this->translate('Preview'),
            ])->add('edit', [
                'url'       => 'director/syncrule/edit',
                'urlParams' => ['id' => $id],
                'label'     => $this->translate('Modify'),
            ])->add('property', [
                'label' => $this->translate('Properties'),
                'url'   => 'director/syncrule/property',
                'urlParams' => ['rule_id' => $id]
            ])->add('history', [
                'label' => $this->translate('History'),
                'url'   => 'director/syncrule/history',
                'urlParams' => ['id' => $id]
            ]);
        } else {
            $this->add('add', [
                'url'       => 'director/syncrule/add',
                'label'     => $this->translate('Sync rule'),
            ]);
        }
    }
}
