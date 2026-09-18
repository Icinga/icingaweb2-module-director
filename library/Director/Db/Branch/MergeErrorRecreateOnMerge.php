<?php

// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Db\Branch;

class MergeErrorRecreateOnMerge extends MergeError
{
    public function prepareMessage()
    {
        return sprintf(
            $this->translate('Cannot recreate %s %s'),
            $this->getObjectTypeName(),
            $this->getNiceObjectName()
        );
    }
}
