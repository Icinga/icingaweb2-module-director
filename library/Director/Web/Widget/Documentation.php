<?php

namespace Icinga\Module\Director\Web\Widget;

use gipfl\IcingaWeb2\Link;
use ipl\I18n\Translation;
use Icinga\Application\ApplicationBootstrap;
use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use ipl\Html\Html;

class Documentation
{
    use Translation;

    /** @var ApplicationBootstrap */
    protected $app;

    /** @var Auth */
    protected $auth;

    public function __construct(ApplicationBootstrap $app, Auth $auth)
    {
        $this->app = $app;
        $this->auth = $auth;
    }

    public static function link($label, $module, $chapter, $title = null)
    {
        $doc = new static(Icinga::app(), Auth::getInstance());
        return $doc->getModuleLink($label, $module, $chapter, $title);
    }

    public function getModuleLink($label, $module, $chapter, $title = null)
    {
        if ($title !== null) {
            $title = sprintf(
                $this->translate('Click to read our documentation: %s'),
                $title
            );
        }
        $linkToGitHub = false;
        $baseParams = [
            'class' => 'icon-book',
            'title' => $title,
        ];
        if ($this->hasAccessToDocumentationModule()) {
            return Link::create(
                $label,
                $this->getDirectorDocumentationUrl($chapter),
                null,
                ['data-base-target' => '_next'] + $baseParams
            );
        }

        $baseParams['target'] = '_blank';
        if ($linkToGitHub) {
            return Html::tag('a', [
                'href' => $this->githubDocumentationUrl($module, $chapter),
            ] + $baseParams, $label);
        }

        return Html::tag('a', [
            'href' => $this->icingaDocumentationUrl($module, $chapter),
        ] + $baseParams, $label);
    }

    protected function getDirectorDocumentationUrl($chapter)
    {
        return 'doc/module/director/chapter/'
            . \preg_replace('/^\d+-/', '', \rawurlencode($chapter));
    }

    protected function githubDocumentationUrl($module, $chapter)
    {
        return sprintf(
            "https://github.com/Icinga/icingaweb2-module-%s/blob/master/doc/%s.md",
            \rawurlencode($module),
            \rawurlencode($chapter)
        );
    }

    protected function icingaDocumentationUrl($module, $chapter)
    {
        return sprintf(
            'https://icinga.com/docs/%s/latest/doc/%s/',
            \rawurlencode($module),
            \rawurlencode($chapter)
        );
    }

    protected function hasAccessToDocumentationModule()
    {
        return $this->app->getModuleManager()->hasLoaded('doc')
            && $this->auth->hasPermission('module/doc');
    }
}
