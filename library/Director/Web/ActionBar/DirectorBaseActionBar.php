<?php

namespace Icinga\Module\Director\Web\ActionBar;

use ipl\Html\Link;
use ipl\Translation\TranslationHelper;
use ipl\Web\Component\ActionBar;
use ipl\Web\Url;

class DirectorBaseActionBar extends ActionBar
{
    use TranslationHelper;

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
        return Link::create(
            $this->translate('back'),
            'director/dashboard',
            ['name' => $this->getPluralBaseType()],
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
