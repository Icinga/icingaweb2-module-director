<?php

use Icinga\Application\Modules\Module;
use Icinga\Module\Director\Application\DependencyChecker;

if (version_compare(PHP_VERSION, '5.6.0') < 0) {
    include __DIR__ . '/run-php5.3.php';
    return;
}

/** @var Module $this */
$checker = new DependencyChecker($this->app);
if (! $checker->satisfiesDependencies($this)) {
    include __DIR__ . '/run-missingdeps.php';
    return;
}

include __DIR__ . '/register-hooks.php';
