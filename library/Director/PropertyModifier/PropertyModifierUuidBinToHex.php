<?php

namespace Icinga\Module\Director\PropertyModifier;

use Icinga\Module\Director\Hook\PropertyModifierHook;
use Ramsey\Uuid\Uuid;

class PropertyModifierUuidBinToHex extends PropertyModifierHook
{
    public function getName()
    {
        return mt('director', 'UUID: from binary to hex');
    }

    public function transform($value)
    {
        return Uuid::fromBytes($value)->toString();
    }
}
