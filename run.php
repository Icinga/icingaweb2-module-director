<?php

$this->registerHook('Monitoring\\HostActions', '\\Icinga\\Module\\Director\\Web\\HostActions');
$this->registerHook('Director\\ImportSource', '\\Icinga\\Module\\Director\\Import\\ImportSourceSql', 'sql');


