<?php

$this->registerHook('Monitoring\\HostActions', '\\Icinga\\Module\\Director\\Web\\HostActions');
$this->registerHook('Director\\ImportSource', '\\Icinga\\Module\\Director\\Import\\ImportSourceSql', 'sql');
$this->registerHook('Director\\ImportSource', '\\Icinga\\Module\\Director\\Import\\ImportSourceLdap', 'ldap');

$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeString', 'string');
$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeNumber', 'number');
$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeTime', 'time');
$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeDatalist', 'datalist');
$this->registerHook('Director\\DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeSqlQuery', 'sqlquery');
