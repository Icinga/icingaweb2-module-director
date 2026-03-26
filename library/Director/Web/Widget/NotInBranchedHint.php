<?php

// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web\Widget;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Widget\Hint;
use Icinga\Authentication\Auth;
use Icinga\Module\Director\Db\Branch\Branch;
use ipl\Html\Html;

class NotInBranchedHint extends Hint
{
    use TranslationHelper;

    public function __construct($forbiddenAction, Branch $branch, Auth $auth)
    {
        parent::__construct(Html::sprintf(
            $this->translate('%s is not available while being in a Configuration Branch: %s'),
            $forbiddenAction,
            Branch::requireHook()->linkToBranch($branch, $auth, $branch->getName())
        ), 'info');
    }
}
