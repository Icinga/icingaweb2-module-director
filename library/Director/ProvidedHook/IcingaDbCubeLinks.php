<?php

namespace Icinga\Module\Director\ProvidedHook;

use Icinga\Data\Filter\Filter;
use Icinga\Exception\ProgrammingError;
use Icinga\Module\Cube\Hook\IcingaDbActionsHook;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Module\Cube\IcingaDb\IcingaDbHostStatusCube;

class IcingaDbCubeLinks extends IcingaDbActionsHook
{
    /**
     * @inheritDoc
     * @param IcingaDbCube $cube
     * @throws ProgrammingError
     */
    public function createActionLinks(IcingaDbCube $cube)
    {
        if (! $cube instanceof IcingaDbHostStatusCube) {
            return;
        }

        $filterChain = $cube->getObjectsFilter();

        if ($filterChain->count() === 1) {
            $url = 'director/host/edit?';
            $params = ['name' => $filterChain->getIterator()->current()->getValue()];

            $title = t('Modify a host');
            $description = sprintf(
                t('This allows you to modify properties for "%s"'),
                $filterChain->getIterator()->current()->getValue()
            );
        } else {
            $params = null;

            $urlFilter = Filter::matchAny();
            foreach ($filterChain as $filter) {
                $urlFilter->addFilter(
                    Filter::matchAny(
                        Filter::expression(
                            'name',
                            '=',
                            $filter->getValue()
                        )
                    )
                );
            }

            $url = 'director/hosts/edit?' . $urlFilter->toQueryString();

            $title = sprintf(t('Modify %d hosts'), $filterChain->count());
            $description = t(
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
