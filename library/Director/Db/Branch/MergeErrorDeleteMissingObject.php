<?php

// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Db\Branch;

class MergeErrorDeleteMissingObject extends MergeError
{
    public function prepareMessage()
    {
        return sprintf(
            $this->translate('Cannot delete %s %s, it does not exist'),
            $this->getObjectTypeName(),
            $this->getNiceObjectName()
        );
    }
}
