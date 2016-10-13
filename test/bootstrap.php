<?php

use Icinga\Application\Cli;

// TODO: fix paths
require_once '/usr/local/icingaweb2/library/Icinga/Application/Cli.php';
require_once dirname(__DIR__) . '/library/Director/Test/BaseTestCase.php';
Cli::start('/usr/local/icingaweb2')->getModuleManager()->loadModule('director');

