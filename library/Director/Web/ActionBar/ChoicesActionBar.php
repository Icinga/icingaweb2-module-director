<?php

namespace Icinga\Module\Director\Web\ActionBar;

use dipl\Html\Link;

class ChoicesActionBar extends DirectorBaseActionBar
{
    protected function assemble()
    {
        $type = $this->type;
        $this->add(
            $this->getBackToDashboardLink()
        )->add(
            Link::create(
                $this->translate('Add'),
                "director/templatechoice/$type",
                ['type' => 'object'],
                [
                    'title' => $this->translate('Create a new template choice'),
                    'class' => 'icon-plus',
                    'data-base-target' => '_next'
                ]
            )
        );
    }
}
