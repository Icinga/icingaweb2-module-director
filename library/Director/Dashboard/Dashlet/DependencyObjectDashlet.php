<?php

namespace Icinga\Module\Director\Dashboard\Dashlet;

class DependencyObjectDashlet extends Dashlet
{
    protected $icon = 'sitemap';

    protected $requiredStats = ['dependency'];

    public function getTitle()
    {
        return $this->translate('Dependencies');
    }

    public function getSummary()
    {
        return $this->translate('Object dependency relationships.')
            . ' ' . parent::getSummary();
    }

    public function getUrl()
    {
        return 'director/dependencies/applyrules';
    }
}
