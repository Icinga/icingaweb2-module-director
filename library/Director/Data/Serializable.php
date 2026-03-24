<?php

// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Data;

use JsonSerializable;

interface Serializable extends JsonSerializable
{
    public static function fromSerialization($value);
}
