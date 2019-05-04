<?php

namespace Icinga\Module\Director\Web\ActionBar;

use dipl\Html\Link;

class ObjectsActionBar extends DirectorBaseActionBar
{
    protected function assemble()
    {
        $type = $this->type;
        $this->add(
            $this->getBackToDashboardLink()
        )->add(
            Link::create(
                $this->translate('Add'),
                "director/$type/add",
                ['type' => 'object'],
                [
                    'title' => $this->translate('Create a new object'),
                    'class' => 'icon-plus',
                    'data-base-target' => '_next'
                ]
            )
        );
    }
}
