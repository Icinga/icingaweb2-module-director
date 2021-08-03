<?php

namespace Icinga\Module\Director\Data;

use JsonSerializable;

interface Serializable extends JsonSerializable
{
    public static function fromSerialization($value);
}
