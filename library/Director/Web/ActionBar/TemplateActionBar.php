<?php

namespace Icinga\Module\Director\Web\ActionBar;

use ipl\Html\Link;

class TemplateActionBar extends DirectorBaseActionBar
{
    protected function assemble()
    {
        $type = $this->type;
        $pltype = preg_replace('/cys$/', 'cies', $type . 's');
        $renderTree = $this->url->getParam('render') === 'tree';
        $renderParams = $renderTree ? null : ['render' => 'tree'];
        $this->add(
            $this->getBackToDashboardLink()
        )->add(
            Link::create(
                $this->translate('Add'),
                "director/$type/add",
                ['type' => 'template'],
                [
                    'title' => $this->translate('Create a new Template'),
                    'class' => 'icon-plus',
                    'data-base-target' => '_next'
                ]
            )
        )->add(
            Link::create(
                $renderTree ? $this->translate('Table') : $this->translate('Tree'),
                "director/$pltype/templates",
                $renderParams,
                [
                    'class' => 'icon-' . ($renderTree ? 'doc-text' : 'sitemap'),
                    'title' => $renderTree
                        ? $this->translate('Switch to Tree view')
                        : $this->translate('Switch to Table view')
                ]
            )
        );
    }
}
