<?php

namespace Icinga\Module\Director\Web\Table\Dependency;

use Icinga\Application\Modules\Module;
use Icinga\Module\Director\Application\DependencyChecker;
use Icinga\Web\Url;

class DependencyInfoTable
{
    protected $module;

    protected $checker;

    public function __construct(DependencyChecker $checker, Module $module)
    {
        $this->module = $module;
        $this->checker = $checker;
    }

    protected function linkToModule($name, $icon)
    {
        return Html::link(
            Html::escape($name),
            Html::webUrl('config/module', ['name' => $name]),
            [
                'class' => "icon-$icon"
            ]
        );
    }

    public function render()
    {
        $html = '<table class="common-table table-row-selectable">
<thead>
<tr>
    <th>' . Html::escape($this->translate('Module name')) . '</th>
    <th>' . Html::escape($this->translate('Required')) . '</th>
    <th>' . Html::escape($this->translate('Installed')) . '</th>
</tr>
</thead>
<tbody data-base-target="_next">
';
        foreach ($this->checker->getDependencies($this->module) as $dependency) {
            $name = $dependency->getName();
            $isLibrary = substr($name, 0, 11) === 'icinga-php-';
            $rowAttributes = $isLibrary ? ['data-base-target' => '_self'] : null;
            if ($dependency->isSatisfied()) {
                if ($dependency->isSatisfied()) {
                    $icon = 'ok';
                } else {
                    $icon = 'cancel';
                }
                $link = $isLibrary ? $this->noLink($name, $icon) : $this->linkToModule($name, $icon);
                $installed = $dependency->getInstalledVersion();
            } elseif ($dependency->isInstalled()) {
                $installed = sprintf('%s (%s)', $dependency->getInstalledVersion(), $this->translate('disabled'));
                $link = $this->linkToModule($name, 'cancel');
            } else {
                $installed = $this->translate('missing');
                $repository = $isLibrary ? $name : "icingaweb2-module-$name";
                $link = sprintf(
                    '%s (%s)',
                    $this->noLink($name, 'cancel'),
                    Html::linkToGitHub(Html::escape($this->translate('more')), 'Icinga', $repository)
                );
            }

            $html .= $this->htmlRow([
                $link,
                Html::escape($dependency->getRequirement()),
                Html::escape($installed)
            ], $rowAttributes);
        }

        return $html . '</tbody>
</table>
';
    }

    protected function noLink($label, $icon)
    {
        return Html::link(Html::escape($label), Url::fromRequest()->with('rnd', rand(1, 100000)), [
            'class' => "icon-$icon"
        ]);
    }

    protected function translate($string)
    {
        return \mt('director', $string);
    }

    protected function htmlRow(array $cols, $rowAttributes)
    {
        $content = '';
        foreach ($cols as $escapedContent) {
            $content .= Html::tag('td', null, $escapedContent);
        }
        return Html::tag('tr', $rowAttributes, $content);
    }
}
