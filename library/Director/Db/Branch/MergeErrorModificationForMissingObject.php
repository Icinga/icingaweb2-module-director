<?php

// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Db\Branch;

class MergeErrorModificationForMissingObject extends MergeError
{
    public function prepareMessage()
    {
        return sprintf(
            $this->translate('Cannot apply modification for %s %s, object does not exist'),
            $this->getObjectTypeName(),
            $this->getNiceObjectName()
        );
    }
}
