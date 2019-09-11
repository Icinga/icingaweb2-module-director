<?php

use Icinga\Application\Icinga;
use Icinga\Exception\IcingaException;
use Icinga\Web\Url;

if (Icinga::app()->isCli()) {
    throw new IcingaException(
        "Missing dependencies, please check "
    );
} else {
    $request = Icinga::app()->getRequest();
    $path = $request->getPathInfo();
    if (! preg_match('#^/director#', $path)) {
        return;
    }
    if (preg_match('#^/director/phperror/dependencies#', $path)) {
        return;
    }

    header('Location: ' . Url::fromPath('director/phperror/dependencies'));
    exit;
}
