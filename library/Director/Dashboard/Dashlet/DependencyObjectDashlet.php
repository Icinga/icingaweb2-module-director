<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class DependencyObjectDashlet extends Dashlet
{
    protected $icon = '';

    protected $requiredStats = array('dependency');

    public function getTitle()
    {
        return $this->translate('Dependencies.');
    }

    public function getSummary()
    {
        return $this->translate('Define object dependency relationships.')
            . ' ' . parent::getSummary();
    }

    public function getUrl()
    {
        return 'director/dependencies';
    }
}
