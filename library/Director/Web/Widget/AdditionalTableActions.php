<?php

namespace Icinga\Module\Director\Web\Widget;

use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use gipfl\IcingaWeb2\Icon;
use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use gipfl\IcingaWeb2\Table\ZfQueryBasedTable;
use gipfl\IcingaWeb2\Url;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Web\Table\FilterableByUsage;

class AdditionalTableActions
{
    use TranslationHelper;

    /** @var Auth */
    protected $auth;

    /** @var Url */
    protected $url;

    /** @var ZfQueryBasedTable */
    protected $table;

    public function __construct(Auth $auth, Url $url, ZfQueryBasedTable $table)
    {
        $this->auth = $auth;
        $this->url = $url;
        $this->table = $table;
    }

    public function appendTo(HtmlDocument $parent)
    {
        $links = [];
        if ($this->hasPermission('director/admin')) {
            $links[] = $this->createDownloadJsonLink();
        }
        if ($this->hasPermission('director/showsql')) {
            $links[] = $this->createShowSqlToggle();
        }

        if ($this->table instanceof FilterableByUsage) {
            $parent->add($this->showUsageFilter($this->table));
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

    protected function showUsageFilter(FilterableByUsage $table)
    {
        $active = $this->url->getParam('usage', 'all');
        $links = [
            Link::create($this->translate('all'), $this->url->without('usage')),
            Link::create($this->translate('used'), $this->url->with('usage', 'used')),
            Link::create($this->translate('unused'), $this->url->with('usage', 'unused')),
        ];

        if ($active === 'used') {
            $table->showOnlyUsed();
        } elseif ($active === 'unused') {
            $table->showOnlyUnUsed();
        }

        $options = $this->ul(
            $this->li([
                Link::create(
                    sprintf($this->translate('Usage (%s)'), $active),
                    '#',
                    null,
                    [
                        'class' => 'icon-sitemap'
                    ]
                ),
                $subUl = Html::tag('ul')
            ]),
            ['class' => 'nav']
        );

        foreach ($links as $link) {
            $subUl->add($this->li($link));
        }

        return $options;
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
