<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Hook\PropertyModifierHook;

interface InstantiatedViaHook
{
    /**
     * @return mixed|PropertyModifierHook|JobHook
     */
    public function getInstance();
}
