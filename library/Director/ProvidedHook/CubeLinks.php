<?php

namespace Icinga\Module\Director\ProvidedHook;

use Icinga\Module\Cube\Hook\ActionLinksHook;
use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Data\Filter\Filter;
use Icinga\Web\View;

class CubeLinks extends ActionLinksHook
{
    public function getHtml(View $view, Cube $cube)
    {
        if (! $cube instanceof IdoHostStatusCube) {
            return '';
        }
        $cube->finalizeInnerQuery();
        $query = $cube->innerQuery()
            ->reset('columns')
            ->columns(array('host' => 'o.name1'))
            ->reset('group');

        $hosts = $cube->db()->fetchCol($query);

        if (count($hosts) === 1) {
            $url = 'director/host/edit';
            $params = array('name' => $hosts[0]);
        } else {
            $params = null;

            $filter = Filter::matchAny();
            foreach($hosts as $host) {
                $filter->addFilter(
                    Filter::matchAny(Filter::expression('name', '=', $host))
                );
            }

            $url = 'director/hosts/edit?' . $filter->toQueryString();
        }

        return $view->qlink(
            $view->translate('Modify hosts'),
            $url,
            $params,
            array('class' => 'icon-wrench')
        );
    }
}
