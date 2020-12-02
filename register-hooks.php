<?php

use Icinga\Application\Modules\Module;
use Icinga\Module\Director\DataType\DataTypeArray;
use Icinga\Module\Director\DataType\DataTypeBoolean;
use Icinga\Module\Director\DataType\DataTypeDatalist;
use Icinga\Module\Director\DataType\DataTypeDirectorObject;
use Icinga\Module\Director\DataType\DataTypeNumber;
use Icinga\Module\Director\DataType\DataTypeSqlQuery;
use Icinga\Module\Director\DataType\DataTypeString;
use Icinga\Module\Director\Import\ImportSourceCoreApi;
use Icinga\Module\Director\Import\ImportSourceDirectorObject;
use Icinga\Module\Director\Import\ImportSourceLdap;
use Icinga\Module\Director\Import\ImportSourceRestApi;
use Icinga\Module\Director\Import\ImportSourceSql;
use Icinga\Module\Director\Job\ConfigJob;
use Icinga\Module\Director\Job\HousekeepingJob;
use Icinga\Module\Director\Job\ImportJob;
use Icinga\Module\Director\Job\SyncJob;
use Icinga\Module\Director\PropertyModifier\PropertyModifierArrayElementByPosition;
use Icinga\Module\Director\PropertyModifier\PropertyModifierArrayFilter;
use Icinga\Module\Director\PropertyModifier\PropertyModifierArrayToRow;
use Icinga\Module\Director\PropertyModifier\PropertyModifierArrayUnique;
use Icinga\Module\Director\PropertyModifier\PropertyModifierBitmask;
use Icinga\Module\Director\PropertyModifier\PropertyModifierCombine;
use Icinga\Module\Director\PropertyModifier\PropertyModifierDnsRecords;
use Icinga\Module\Director\PropertyModifier\PropertyModifierExtractFromDN;
use Icinga\Module\Director\PropertyModifier\PropertyModifierFromAdSid;
use Icinga\Module\Director\PropertyModifier\PropertyModifierFromLatin1;
use Icinga\Module\Director\PropertyModifier\PropertyModifierGetHostByAddr;
use Icinga\Module\Director\PropertyModifier\PropertyModifierGetHostByName;
use Icinga\Module\Director\PropertyModifier\PropertyModifierGetPropertyFromOtherImportSource;
use Icinga\Module\Director\PropertyModifier\PropertyModifierJoin;
use Icinga\Module\Director\PropertyModifier\PropertyModifierJsonDecode;
use Icinga\Module\Director\PropertyModifier\PropertyModifierLConfCustomVar;
use Icinga\Module\Director\PropertyModifier\PropertyModifierListToObject;
use Icinga\Module\Director\PropertyModifier\PropertyModifierLowercase;
use Icinga\Module\Director\PropertyModifier\PropertyModifierMakeBoolean;
use Icinga\Module\Director\PropertyModifier\PropertyModifierMap;
use Icinga\Module\Director\PropertyModifier\PropertyModifierNegateBoolean;
use Icinga\Module\Director\PropertyModifier\PropertyModifierParseURL;
use Icinga\Module\Director\PropertyModifier\PropertyModifierRegexReplace;
use Icinga\Module\Director\PropertyModifier\PropertyModifierRegexSplit;
use Icinga\Module\Director\PropertyModifier\PropertyModifierRejectOrSelect;
use Icinga\Module\Director\PropertyModifier\PropertyModifierRenameColumn;
use Icinga\Module\Director\PropertyModifier\PropertyModifierReplace;
use Icinga\Module\Director\PropertyModifier\PropertyModifierSkipDuplicates;
use Icinga\Module\Director\PropertyModifier\PropertyModifierSplit;
use Icinga\Module\Director\PropertyModifier\PropertyModifierStripDomain;
use Icinga\Module\Director\PropertyModifier\PropertyModifierSubstring;
use Icinga\Module\Director\PropertyModifier\PropertyModifierToInt;
use Icinga\Module\Director\PropertyModifier\PropertyModifierTrim;
use Icinga\Module\Director\PropertyModifier\PropertyModifierUppercase;
use Icinga\Module\Director\PropertyModifier\PropertyModifierUpperCaseFirst;
use Icinga\Module\Director\PropertyModifier\PropertyModifierURLEncode;
use Icinga\Module\Director\PropertyModifier\PropertyModifierUuidBinToHex;
use Icinga\Module\Director\PropertyModifier\PropertyModifierXlsNumericIp;
use Icinga\Module\Director\ProvidedHook\CubeLinks;

/** @var Module $this */
$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');
$this->provideHook('cube/Actions', CubeLinks::class);

$directorHooks = [
    'director/DataType' => [
        DataTypeArray::class,
        DataTypeBoolean::class,
        DataTypeDatalist::class,
        DataTypeNumber::class,
        DataTypeDirectorObject::class,
        DataTypeSqlQuery::class,
        DataTypeString::class
    ],
    'director/ImportSource' => [
        ImportSourceDirectorObject::class,
        ImportSourceSql::class,
        ImportSourceLdap::class,
        ImportSourceCoreApi::class,
        ImportSourceRestApi::class
    ],
    'director/Job' => [
        ConfigJob::class,
        HousekeepingJob::class,
        ImportJob::class,
        SyncJob::class,
    ],
    'director/PropertyModifier' => [
        PropertyModifierArrayElementByPosition::class,
        PropertyModifierArrayFilter::class,
        PropertyModifierArrayToRow::class,
        PropertyModifierArrayUnique::class,
        PropertyModifierBitmask::class,
        PropertyModifierCombine::class,
        PropertyModifierDnsRecords::class,
        PropertyModifierExtractFromDN::class,
        PropertyModifierFromAdSid::class,
        PropertyModifierFromLatin1::class,
        PropertyModifierGetHostByAddr::class,
        PropertyModifierGetHostByName::class,
        PropertyModifierGetPropertyFromOtherImportSource::class,
        PropertyModifierJoin::class,
        PropertyModifierJsonDecode::class,
        PropertyModifierLConfCustomVar::class,
        PropertyModifierListToObject::class,
        PropertyModifierLowercase::class,
        PropertyModifierMakeBoolean::class,
        PropertyModifierMap::class,
        PropertyModifierNegateBoolean::class,
        PropertyModifierParseURL::class,
        PropertyModifierRegexReplace::class,
        PropertyModifierRegexSplit::class,
        PropertyModifierRejectOrSelect::class,
        PropertyModifierRenameColumn::class,
        PropertyModifierReplace::class,
        PropertyModifierSkipDuplicates::class,
        PropertyModifierSplit::class,
        PropertyModifierStripDomain::class,
        PropertyModifierSubstring::class,
        PropertyModifierToInt::class,
        PropertyModifierTrim::class,
        PropertyModifierUppercase::class,
        PropertyModifierUpperCaseFirst::class,
        PropertyModifierURLEncode::class,
        PropertyModifierUuidBinToHex::class,
        PropertyModifierXlsNumericIp::class,
    ]
];

foreach ($directorHooks as $type => $classNames) {
    foreach ($classNames as $className) {
        $this->provideHook($type, $className);
    }
}
