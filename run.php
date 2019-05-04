<?php

use Icinga\Application\Modules\Module;
use dipl\Loader\CompatLoader;

if (version_compare(PHP_VERSION, '5.4.0') < 0) {
    include __DIR__ . '/run-php5.3.php';
    return;
}

$prefix = '\\Icinga\\Module\\Director\\';

/** @var Module $this */
$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');

$this->provideHook('director/ImportSource', $prefix . 'Import\\ImportSourceSql');
$this->provideHook('director/ImportSource', $prefix . 'Import\\ImportSourceLdap');
$this->provideHook('director/ImportSource', $prefix . 'Import\\ImportSourceCoreApi');
$this->provideHook('director/ImportSource', $prefix . 'Import\\ImportSourceRestApi');

$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeArray');
$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeBoolean');
$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeDatalist');
$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeNumber');
$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeDirectorObject');
$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeSqlQuery');
$this->provideHook('director/DataType', $prefix . 'DataType\\DataTypeString');

$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierLowercase');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierRegexReplace');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierRegexSplit');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierReplace');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierStripDomain');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierSubstring');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierUppercase');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierMap');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierSplit');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierJoin');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierGetHostByName');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierGetIPv4ByName');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierDnsRecords');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierExtractFromDN');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierFromAdSid');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierFromLatin1');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierBitmask');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierMakeBoolean');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierJsonDecode');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierToInt');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierLConfCustomVar');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierArrayFilter');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierCombine');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierXlsNumericIp');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierURLEncode');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierUpperCaseFirst');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierRejectOrSelect');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierArrayElementByPosition');
$this->provideHook('director/PropertyModifier', $prefix . 'PropertyModifier\\PropertyModifierParseURL');

$this->provideHook('director/Job', $prefix . 'Job\\HousekeepingJob');
$this->provideHook('director/Job', $prefix . 'Job\\ConfigJob');
$this->provideHook('director/Job', $prefix . 'Job\\ImportJob');
$this->provideHook('director/Job', $prefix . 'Job\\SyncJob');

$this->provideHook('cube/Actions', 'CubeLinks');

require_once __DIR__ . '/library/vendor/ipl/Loader/CompatLoader.php';
CompatLoader::delegateLoadingToIcingaWeb($this->app);
