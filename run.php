<?php

$this->registerHook('Monitoring\\HostActions', '\\Icinga\\Module\\Director\\Web\\HostActions');
$this->registerHook('Director\\ImportSource', '\\Icinga\\Module\\Director\\Import\\ImportSourceSql', 'sql');

$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeString', 'string');
$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeNumber', 'number');
$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeTime', 'time');
