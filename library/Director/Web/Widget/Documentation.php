<?php

namespace Icinga\Module\Director\Web\Widget;

use gipfl\IcingaWeb2\Link;
use gipfl\Translation\TranslationHelper;
use Icinga\Application\ApplicationBootstrap;
use Icinga\Authentication\Auth;
use ipl\Html\Html;

class Documentation
{
    use TranslationHelper;

    /** @var ApplicationBootstrap */
    protected $app;

    /** @var Auth */
    protected $auth;

    public function __construct(ApplicationBootstrap $app, Auth $auth)
    {
        $this->app = $app;
        $this->auth = $auth;
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
        $hasModule = $this->app->getModuleManager()->hasLoaded($module);
        if ($hasModule && $this->hasAccessToDocumentationModule()) {
            return Link::create(
                $label,
                'doc/module/director/chapter/' . \preg_replace('/^\d+-/', '', \rawurlencode($chapter)),
                null,
                [
                    'data-base-target' => '_next',
                    'class'            => 'icon-book',
                    'title'            => $title,
                ]
            );
        } elseif ($linkToGitHub) {
            return Html::tag('a', [
                'href'   => $this->githubDocumentationUrl($module, $chapter),
                'target' => '_blank',
                'title'  => $title,
            ], $label);
        } else {
            return Html::tag('a', [
                'href'   => $this->icingaDocumentationUrl($module, $chapter),
                'target' => '_blank',
                'title'  => $title,
            ], $label);
        }
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
