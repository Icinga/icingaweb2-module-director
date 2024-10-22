<?php

use Icinga\Application\Icinga;
use Icinga\Exception\IcingaException;
use Icinga\Web\Url;

if (Icinga::app()->isCli()) {
    throw new IcingaException(
        "PHP version 5.6.x is required for Director >= 1.7.0, you're running %s."
        . ' Please either upgrade PHP or downgrade Icinga Director',
        PHP_VERSION
    );
} else {
    $request = Icinga::app()->getRequest();
    $path = $request->getPathInfo();
    if (! preg_match('#^/director#', $path)) {
        return;
    }
    if (preg_match('#^/director/phperror/error#', $path)) {
        return;
    }

    header('Location: ' . Url::fromPath('director/phperror/error'));
    exit;
}
