<?php

namespace Icinga\Module\Director\RestApi;

use Icinga\Module\Director\Data\Exporter;
use Icinga\Web\Request;
use InvalidArgumentException;

class RestApiParams
{
    public static function applyParamsToExporter(Exporter $exporter, Request $request, $shortObjectType = null)
    {
        $params = $request->getUrl()->getParams();
        $resolved = (bool) $params->get('resolved', false);
        $withNull = $params->shift('withNull');
        if ($params->get('withServices')) {
            if ($shortObjectType !== 'host') {
                throw new InvalidArgumentException('withServices is available for Hosts only');
            }
            $exporter->enableHostServices();
        }
        /** @var ?string $properties */
        $properties = $params->shift('properties');
        if ($properties) {
            $exporter->filterProperties(preg_split('/\s*,\s*/', $properties, -1, PREG_SPLIT_NO_EMPTY));
        }
        $exporter->resolveObjects($resolved);
        $exporter->showDefaults($withNull);
    }
}
