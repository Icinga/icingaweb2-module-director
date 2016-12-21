<?php

namespace Icinga\Module\Director\ProvidedHook;

use Icinga\Module\Cube\Hook\ActionsHook;
use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Data\Filter\Filter;
use Icinga\Web\View;

class CubeLinks extends ActionsHook
{
    public function prepareActionLinks(Cube $cube, View $view)
    {
        if (! $cube instanceof IdoHostStatusCube) {
            return;
        }

        $cube->finalizeInnerQuery();
        $query = $cube->innerQuery()
            ->reset('columns')
            ->columns(array('host' => 'o.name1'))
            ->reset('group');

        $hosts = $cube->db()->fetchCol($query);

        $count = count($hosts);
        if ($count === 1) {
            $url = 'director/host/edit';
            $params = array('name' => $hosts[0]);

            $title = $view->translate('Modify a host');
            $description = sprintf(
                $view->translate('This allows you to modify properties for "%s"'),
                $hosts[0]
            );
        } else {
            $params = null;

            $filter = Filter::matchAny();
            foreach($hosts as $host) {
                $filter->addFilter(
                    Filter::matchAny(Filter::expression('name', '=', $host))
                );
            }

            $url = 'director/hosts/edit?' . $filter->toQueryString();

            $title = sprintf($view->translate('Modify %d hosts'), $count);
            $description = $view->translate(
                'This allows you to modify properties for all chosen hosts at once'
            );
        }

        $this->addActionLink(
            $this->makeUrl($url, $params),
            $title,
            $description,
            'wrench'
        );
    }
}
