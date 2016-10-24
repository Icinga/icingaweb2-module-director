<?php

namespace Icinga\Module\Director\Clicommands;

use Icinga\Module\Director\Cli\Command;
use Icinga\Module\Director\Objects\IcingaHost;

class TemplateresolverCommand extends Command
{
    public function testAction()
    {
        IcingaHost::fetchAllFullyResolved($this->db());
    }
}
