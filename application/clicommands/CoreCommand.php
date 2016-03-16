<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\PlainObjectRenderer;

class CoreCommand extends Command
{
    public function constantsAction()
    {
        foreach ($this->api()->getConstants() as $name => $value) {
            printf("const %s = %s\n", $name, PlainObjectRenderer::render($value));
        }
    }
}
