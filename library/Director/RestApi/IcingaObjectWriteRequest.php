<?php

namespace Icinga\Module\Director\RestApi;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\UrlParams;

/**
 * A single request to create, update, or set variables on an IcingaObject,
 * as submitted through the REST API
 */
final readonly class IcingaObjectWriteRequest
{
    /**
     * @param ?IcingaObject $object The object loaded/matched for this request, or
     *                              null when none was found and $type/$data must be
     *                              used to create one
     * @param array $data Remaining request body, with any vars/vars.* keys already
     *                    extracted into $overRiddenCustomVars
     * @param string $type Short table name of the object type, e.g. "host"
     * @param string $actionName The dispatched action, e.g. "index" or "variables"
     * @param string $method HTTP method, "POST" or "PUT"
     * @param bool $replaceAll Whether a POST body's "vars" is a full replacement
     * @param array<string, mixed> $overRiddenCustomVars key => value, value can be a
     *        scalar or a nested array/object for dictionary and array typed vars
     * @param bool $allowsOverrides Whether the "allowOverrides" request param was set
     * @param UrlParams $params The request's URL parameters
     */
    public function __construct(
        public ?IcingaObject $object,
        public array $data,
        public string $type,
        public string $actionName,
        public string $method,
        public bool $replaceAll,
        public array $overRiddenCustomVars,
        public bool $allowsOverrides,
        public UrlParams $params
    ) {
    }
}
