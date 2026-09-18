<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\CoreBeta;

abstract class Stream
{
    protected $stream;

    protected $buffer = '';

    protected $bufferLength = 0;

    protected function __construct($stream)
    {
        $this->stream = $stream;
    }
}
