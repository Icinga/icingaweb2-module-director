<?php

namespace Icinga\Module\Director\Web\Widget;

use dipl\Html\Html;
use dipl\Html\Icon;
use dipl\Html\Link;
use dipl\Translation\TranslationHelper;
use dipl\Web\Url;
use Icinga\Authentication\Auth;

class AdditionalTableActions
{
    use TranslationHelper;

    /** @var Auth */
    protected $auth;

    /** @var Url */
    protected $url;

    public function __construct(Auth $auth, Url $url)
    {
        $this->auth = $auth;
        $this->url = $url;
    }

    public function appendTo(Html $parent)
    {
        $links = [];
        if ($this->hasPermission('director/admin')) {
            $links[] = $this->createDownloadJsonLink();
        }
        if ($this->hasPermission('director/showsql')) {
            $links[] = $this->createShowSqlToggle();
        }

        if (! empty($links)) {
            $parent->add($this->moreOptions($links));
        }

        return $this;
    }

    protected function createDownloadJsonLink()
    {
        return Link::create(
            $this->translate('Download as JSON'),
            $this->url->with('format', 'json'),
            null,
            ['target' => '_blank']
        );
    }

    protected function createShowSqlToggle()
    {
        if ($this->url->getParam('format') === 'sql') {
            $link = Link::create(
                $this->translate('Hide SQL'),
                $this->url->without('format')
            );
        } else {
            $link = Link::create(
                $this->translate('Show SQL'),
                $this->url->with('format', 'sql')
            );
        }

        return $link;
    }

    protected function moreOptions($links)
    {
        $options = $this->ul(
            $this->li([
                // TODO: extend link for dropdown-toggle from Web 2, doesn't
                // seem to work: [..], null, ['class' => 'dropdown-toggle']
                Link::create(Icon::create('down-open'), '#'),
                $subUl = Html::tag('ul')
            ]),
            ['class' => 'nav']
        );

        foreach ($links as $link) {
            $subUl->add($this->li($link));
        }

        return $options;
    }

    protected function ulLi($content)
    {
        return $this->ul($this->li($content));
    }

    protected function ul($content, $attributes = null)
    {
        return Html::tag('ul', $attributes, $content);
    }

    protected function li($content)
    {
        return Html::tag('li', null, $content);
    }

    protected function hasPermission($permission)
    {
        return $this->auth->hasPermission($permission);
    }
}
