<?php

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
