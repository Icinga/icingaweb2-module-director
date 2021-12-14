<?php

namespace Icinga\Module\Director\ProvidedHook;

use gipfl\Web\Widget\Hint;
use Icinga\Application\Config;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Cube\BaseCube;
use Icinga\Module\Cube\Hook\IcingadbHook;
use Icinga\Module\Cube\HostCube;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Web\View;

class IcingaDbCubeLinks extends IcingadbHook
{
    /**
     * @inheritDoc
     * @param BaseCube $cube
     * @param View $view
     * @throws ProgrammingError
     */
    public function prepareActionLinks(BaseCube $cube, View $view)
    {
        if (! $cube instanceof HostCube) {
            return;
        }

        $hosts = [];
        foreach ($cube->getQuery()->getHostNames($cube->getSlices()) as $host) {
            $hosts[] = $host;
        }

        $count = count($hosts);
        if ($count === 1) {
            $url = 'director/host/edit';
            $params = array('name' => $hosts[0]);
            $title = $view->translate('Modify a host');
            $description = sprintf(
                $view->translate('This allows you to modify properties for "%s" (deployed from director)'),
                $hosts[0]
            );
        } else {
            $params = null;

            $filter = Filter::matchAny();
            foreach ($hosts as $host) {
                if (IcingaHost::exists(['object_name' => $host], $this->directorDb())) {
                    $filter->addFilter(
                        Filter::matchAny(Filter::expression('name', '=', $host))
                    );
                }
            }

            $url = 'director/hosts/edit?' . $filter->toQueryString();
            $title = sprintf($view->translate('Modify %d hosts'), count($hosts));
            $description = $view->translate(
                'This allows you to modify properties for all chosen hosts (deployed from director) at once'
            );
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
