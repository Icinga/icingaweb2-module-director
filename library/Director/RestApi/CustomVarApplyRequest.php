<?php

namespace Icinga\Module\Director\RestApi;

use Icinga\Module\Director\Objects\IcingaObject;

/**
 * A single request to apply a map of custom variable overrides to an
 * IcingaObject, as submitted through the REST API
 */
final readonly class CustomVarApplyRequest
{
    /**
     * @param IcingaObject $object The object the overrides apply to
     * @param array<string, mixed> $overRiddenCustomVars key => value, value can be a
     *        scalar or a nested array/object for dictionary and array typed vars
     * @param string $actionName Endpoint name, e.g. "index" or "variables"
     * @param string $method HTTP method, "POST" or "PUT"
     * @param bool $replaceAll Whether this is a full "vars" replacement
     */
    public function __construct(
        public IcingaObject $object,
        public array $overRiddenCustomVars,
        public string $actionName,
        public string $method,
        public bool $replaceAll
    ) {
    }
}
