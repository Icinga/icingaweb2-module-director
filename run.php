<?php

$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');
$this->registerHook('Director\\ImportSource', '\\Icinga\\Module\\Director\\Import\\ImportSourceSql', 'sql');
$this->registerHook('Director\\ImportSource', '\\Icinga\\Module\\Director\\Import\\ImportSourceLdap', 'ldap');
$this->provideHook('director/ImportSource', '\\Icinga\\Module\\Director\\Import\\ImportSourceCoreApi');

$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeString', 'string');
$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeNumber', 'number');
$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeTime', 'time');
$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeDatalist', 'datalist');
$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeSqlQuery', 'sqlquery');
