<?php

namespace Icinga\Module\Director\ProvidedHook;

use Icinga\Application\Config;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Cube\BaseCube;
use Icinga\Module\Cube\Hook\IcingadbHook;
use Icinga\Module\Cube\HostCube;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaObject;
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
        if ( ! $cube instanceof HostCube) {
            return;
        }

        $directorHosts = array_keys(IcingaObject::loadAllByType('Host', $this->directorDb()));

        $hosts = [];
        foreach ($cube->getQuery()->getHostNames($cube->getSlices()) as $host) {
            $hosts[] = $host;
        }

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
                    $chosenHosts[0]
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

            $url = 'director/hosts/edit?' . $filter->toQueryString();

            if (count($chosenHosts) == 1) {
                $title = $view->translate('Modify a host');
                $description = sprintf(
                    $view->translate('This allows you to modify properties for "%s" (deployed from director)'),
                    $chosenHosts[0]
                );
            } else {
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
