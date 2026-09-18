<?php

// SPDX-FileCopyrightText: 2020 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Web;

use Icinga\Web\Window as WebWindow;

class Window extends WebWindow
{
    public function __construct($id)
    {
        parent::__construct(\preg_replace('/_.+$/', '', $id));
    }
}
