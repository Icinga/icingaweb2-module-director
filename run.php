<?php

$prefix = '\\Icinga\\Module\\Director\\';

$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');

$this->provideHook('director/ImportSource', $prefix . 'Import\\ImportSourceSql');
$this->provideHook('director/ImportSource', $prefix . 'Import\\ImportSourceLdap');
$this->provideHook('director/ImportSource', $prefix . 'Import\\ImportSourceCoreApi');

$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeString');
$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeNumber');
$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeTime');
$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeDatalist');
$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeSqlQuery');

$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierLowercase');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierRegexReplace');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierReplace');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierStripDomain');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierSubstring');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierUppercase');
