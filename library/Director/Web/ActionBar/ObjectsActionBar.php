<?php

namespace Icinga\Module\Director\Web\ActionBar;

use gipfl\IcingaWeb2\Link;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Util;

class ObjectsActionBar extends DirectorBaseActionBar
{
    protected function assemble()
    {
        $type = $this->type;
        if ($this->hasPermission('director/' . $type . '_create')) {
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
    } else {
        $this->add($this->getBackToDashboardLink()
    );
    }
    }
    

        /**
     * @param  string $permission
     * @return bool
     */
    public function hasPermission($permission)
    {
        return Util::hasPermission($permission);
    }
}
