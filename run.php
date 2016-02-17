<?php

$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');

$this->provideHook('director/ImportSource', '\\Icinga\\Module\\Director\\Import\\ImportSourceSql');
$this->provideHook('director/ImportSource', '\\Icinga\\Module\\Director\\Import\\ImportSourceLdap');
$this->provideHook('director/ImportSource', '\\Icinga\\Module\\Director\\Import\\ImportSourceCoreApi');

$this->provideHook('director/DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeString');
$this->provideHook('director/DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeNumber');
$this->provideHook('director/DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeTime');
$this->provideHook('director/DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeDatalist');
$this->provideHook('director/DataType', '\\Icinga\\Module\\Director\\DataType\\DataTypeSqlQuery');
