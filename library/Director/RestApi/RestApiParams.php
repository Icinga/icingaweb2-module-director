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
        $withServices = (bool) $params->get('withServices');
        if ($withServices) {
            if ($shortObjectType !== 'host') {
                throw new InvalidArgumentException('withServices is available for Hosts only');
            }
            $exporter->enableHostServices();
        }
        $properties = $params->shift('properties');
        if ($properties !== null && strlen($properties)) {
            $exporter->filterProperties(preg_split('/\s*,\s*/', $properties, -1, PREG_SPLIT_NO_EMPTY));
        }
        $exporter->resolveObjects($resolved);
        $exporter->showDefaults($withNull);
    }
}
