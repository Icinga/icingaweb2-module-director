<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Objects;

interface ObjectWithArguments
{
    /**
     * @return boolean
     */
    public function gotArguments();

    /**
     * @return IcingaArguments
     */
    public function arguments();

    public function unsetArguments();
}
