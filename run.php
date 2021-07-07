<?php

use Icinga\Application\Modules\Module;
use Icinga\Application\Version;

if (version_compare(PHP_VERSION, '5.6.0') < 0) {
    include __DIR__ . '/run-php5.3.php';
    return;
}

if (version_compare(Version::VERSION, '2.9.0') < 0) {
    /** @var Module $this */
    $modules = $this->app->getModuleManager();
    foreach ($this->getDependencies() as $module => $required) {
        if ($modules->hasEnabled($module)) {
            $installed = $modules->getModule($module, false)->getVersion();
            $installed = ltrim($installed, 'v'); // v0.6.0 VS 0.6.0
            if (preg_match('/^([<>=]+)\s*v?(\d+\.\d+\.\d+)$/', $required, $match)) {
                $operator = $match[1];
                $vRequired = $match[2];
                if (version_compare($installed, $vRequired, $operator)) {
                    continue;
                }
            }
        }

        include __DIR__ . '/run-missingdeps.php';

        return;
    }
}

include __DIR__ . '/register-hooks.php';
