<?php

namespace Icinga\Module\Director\Web\ActionBar;

use Icinga\Module\Director\Dashboard\Dashboard;
use gipfl\IcingaWeb2\Link;
use ipl\I18n\Translation;
use gipfl\IcingaWeb2\Widget\ActionBar;
use gipfl\IcingaWeb2\Url;

class DirectorBaseActionBar extends ActionBar
{
    use Translation;

    /** @var Url */
    protected $url;

    /** @var string */
    protected $type;

    public function __construct($type, Url $url)
    {
        $this->type = $type;
        $this->url = $url;
    }

    protected function getBackToDashboardLink()
    {
        $name = $this->getPluralBaseType();
        if (! Dashboard::exists($name)) {
            return null;
        }

        return Link::create(
            $this->translate('back'),
            'director/dashboard',
            ['name' => $name],
            [
                'title' => sprintf(
                    $this->translate('Go back to "%s" Dashboard'),
                    $this->translate(ucfirst($this->type))
                ),
                'class' => 'icon-left-big',
                'data-base-target' => '_main'
            ]
        );
    }

    protected function getBaseType()
    {
        if (substr($this->type, -5) === 'Group') {
            return substr($this->type, 0, -5);
        } else {
            return $this->type;
        }
    }

    protected function getPluralType()
    {
        return $this->type . 's';
    }

    protected function getPluralBaseType()
    {
        return $this->getBaseType() . 's';
    }
}
