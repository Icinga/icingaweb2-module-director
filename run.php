<?php

if (version_compare(PHP_VERSION, '5.6.0') < 0) {
    include __DIR__ . '/run-php5.3.php';
    return;
}

include __DIR__ . '/register-hooks.php';
