<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbObject;

abstract class IcingaObjectField extends DbObject
{
    /**
     *
     * @param Filter|string $filter
     *
     * @return $this
     * @codingStandardsIgnoreStart
     */
    protected function setVar_filter($value)
    {
        // @codingStandardsIgnoreEnd
        if ($value instanceof Filter) {
            $value = $value->toQueryString();
        }

        return $this->reallySet('var_filter', $value);
    }
}
