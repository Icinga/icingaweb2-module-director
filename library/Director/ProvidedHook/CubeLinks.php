<?php

namespace Icinga\Module\Director\ProvidedHook;

use Icinga\Application\Config;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Hook\ActionsHook;
use Icinga\Module\Cube\Ido\IdoHostStatusCube;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\View;

class CubeLinks extends ActionsHook
{
    /**
     * @inheritdoc
     */
    public function prepareActionLinks(Cube $cube, View $view)
    {
        if (! $cube instanceof IdoHostStatusCube) {
            return;
        }

        $directorHosts = array_keys(IcingaObject::loadAllByType('Host', $this->directorDb()));

        $cube->finalizeInnerQuery();
        $query = $cube->innerQuery()
            ->reset('columns')
            ->columns(array('host' => 'o.name1'))
            ->reset('group');

        $hosts = $cube->db()->fetchCol($query);

        $count = count($hosts);
        $chosenHosts = [];
        if ($count === 1) {
            $url = 'director/host/edit';
            if (in_array($hosts[0], $directorHosts)) {
                $chosenHosts[] = $hosts[0];
                $params = array('name' => $hosts[0]);

                $title = $view->translate('Modify a host');
                $description = sprintf(
                    $view->translate('This allows you to modify properties for "%s" (deployed from director)'),
                    $hosts[0]
                );
            }
        } else {
            $params = null;

            $filter = Filter::matchAny();
            foreach ($hosts as $host) {
                if (in_array($host, $directorHosts)) {
                    $chosenHosts[] = $host;
                    $filter->addFilter(
                        Filter::matchAny(Filter::expression('name', '=', $host))
                    );
                }
            }

            if (count($chosenHosts) == 1) {
                $url = 'director/host/edit';
                $params = array('name' => $chosenHosts[0]);

                $title = $view->translate('Modify a host');
                $description = sprintf(
                    $view->translate('This allows you to modify properties for "%s" (deployed from director)'),
                    $chosenHosts[0]
                );
            } else {
                $url = 'director/hosts/edit?' . $filter->toQueryString();

                $title = sprintf($view->translate('Modify %d hosts'), count($chosenHosts));
                $description = $view->translate(
                    'This allows you to modify properties for all chosen hosts (deployed from director) at once'
                );
            }
        }

        if (! (count($chosenHosts) > 0)) {
            return;
        }

        $this->addActionLink(
            $this->makeUrl($url, $params),
            $title,
            $description,
            'wrench'
        );
    }

    protected function directorDb()
    {
        $resourceName = Config::module('director')->get('db', 'resource');
        if (! $resourceName) {
            return false;
        }

        return Db::fromResourceName($resourceName);
    }
}
