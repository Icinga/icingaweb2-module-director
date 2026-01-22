<?php

namespace Icinga\Module\Director\ProvidedHook\Icingadb;

use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\CustomVariable\CustomVariableArray;
use Icinga\Module\Director\Daemon\Logger;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Db\AppliedServiceSetLoader;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaTemplateResolver;
use Icinga\Module\Director\Web\Form\IcingaObjectFieldLoader;
use Icinga\Module\Director\Web\Table\IcingaHostAppliedServicesTable;
use Icinga\Module\Icingadb\Hook\CustomVarRendererHook;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Html\Attributes;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\Html\ValidHtml;
use ipl\Orm\Model;
use PDO;
use Throwable;

class CustomVarRenderer extends CustomVarRendererHook
{
    /** @var array Related datafield configuration */
    protected $fieldConfig = [];

    /** @var array Related custom property configuration */
    protected $customPropertyConfig = [];

    /** @var array Related datalists and their keys and values */
    protected $datalistMaps = [];

    /** @var array Related dictionary field names */
    protected $dictionaryNames = [];

    protected $dictionaryLevel = 0;

    /** @var HtmlElement Table for dictionary fields */
    private $dictionaryTable;

    /** @var HtmlElement Table body for dictionary fields */
    private $dictionaryBody;

    /**
     * Get a database connection to the director database
     *
     * @return Db
     *
     * @throws ConfigurationError
     */
    protected function db(): Db
    {
        $resourceName = Config::module('director')->get('db', 'resource');
        if (! $resourceName) {
            throw new ConfigurationError('Cannot identify director db. No resource configured');
        }

        return Db::fromResourceName($resourceName);
    }

    public function prefetchForObject(Model $object): bool
    {
        try {
            if ($object instanceof Host) {
                $host = $object;
                $service = null;
            } elseif ($object instanceof Service) {
                $host = $object->host;
                $service = $object;
            } else {
                return false;
            }

            $db = $this->db();

            try {
                $directorHostObj = IcingaHost::load($host->name, $db);
                $directorServiceObj = null;
                if ($service !== null) {
                    $serviceOrigin = ['direct', 'applied', 'inherited', 'service-set'];
                    $serviceName = $service->name;
                    $i = 0;
                    do {
                        if ($i > 3) {
                            Logger::error("Failed to find service '%s' on host '%s'", $serviceName, $host->name);

                            return false;
                        } elseif ($serviceOrigin[$i] === 'direct') {
                            $directorServiceObj = IcingaService::loadOptional([
                                'host_id'     => $directorHostObj->get('id'),
                                'object_name' => $serviceName
                            ], $db);
                        } elseif ($serviceOrigin[$i] === 'inherited') {
                            $templateResolver =  new IcingaTemplateResolver($directorHostObj);

                            $parentIds = $templateResolver->listParentIds();

                            $query = $db->getDbAdapter()->select()->from('icinga_service')
                                ->where('object_name = ?', $serviceName)
                                ->where('host_id IN (?)', $parentIds);

                            $directorServices = IcingaService::loadAll(
                                $db,
                                $query,
                                'object_name'
                            );

                            $directorServiceObj = current($directorServices);
                        } elseif ($serviceOrigin[$i] === 'applied') {
                            $appliedFilterQuery = IcingaHostAppliedServicesTable::load($directorHostObj)->getQuery();

                            foreach ($appliedFilterQuery->fetchAll() as $appliedService) {
                                if ($appliedService->apply_for === null) {
                                    $isAppliedService = $appliedService->name === $serviceName;
                                } else {
                                    /** @var ?CustomVariableArray $hostVar */
                                    $hostVar = $directorHostObj->vars()->get((substr($appliedService->apply_for, 10)));
                                    if ($hostVar) {
                                        $appliedServiceName = $appliedService->name;
                                        $appliedForServiceLookup = [];
                                        foreach ($hostVar->getValue() as $val) {
                                            $appliedForServiceLookup[$appliedServiceName . $val] = true;
                                        }

                                        $isAppliedService = isset($appliedForServiceLookup[$serviceName]);
                                    } else {
                                        $isAppliedService = false;
                                    }
                                }

                                if (! $isAppliedService) {
                                    continue;
                                }

                                $query = $db->getDbAdapter()->select()->from('icinga_service')
                                    ->where('object_name = ?', $appliedService->name)
                                    ->where("object_type = 'apply'")
                                    ->where('assign_filter = ?', $appliedService->assign_filter);

                                $directorAppliedServices = IcingaService::loadAll(
                                    $db,
                                    $query,
                                    'object_name'
                                );

                                $directorServiceObj = current($directorAppliedServices);

                                break;
                            }
                        } elseif ($serviceOrigin[$i] === 'service-set') {
                            $templateResolver =  new IcingaTemplateResolver($directorHostObj);

                            $hostServiceSets = $directorHostObj->fetchServiceSets()
                                + AppliedServiceSetLoader::fetchForHost($directorHostObj);

                            $parents = $templateResolver->fetchParents();

                            foreach ($parents as $parent) {
                                $hostServiceSets += $parent->fetchServiceSets();
                                $hostServiceSets += AppliedServiceSetLoader::fetchForHost($parent);
                            }

                            foreach ($hostServiceSets as $hostServiceSet) {
                                foreach ($hostServiceSet->getServiceObjects() as $setServiceObject) {
                                    if ($setServiceObject->getObjectName() === $serviceName) {
                                        $directorServiceObj = $setServiceObject;

                                        break 2;
                                    }
                                }
                            }
                        }

                        $i++;
                    } while (! $directorServiceObj);
                }
            } catch (NotFoundError $_) {
                if ($service !== null) {
                    Logger::error("Failed to find service '%s' on host '%s'", $service->name, $host->name);
                } else {
                    Logger::error('Failed to find host %s', $host->name);
                }

                return false;
            }

            if ($service === null) {
                $fields = (new IcingaObjectFieldLoader($directorHostObj))->getFields();
            } else {
                $fields = (new IcingaObjectFieldLoader($directorServiceObj))->getFields();
            }

            $customProperties = [];
            if (empty($fields)) {
                if ($service === null) {
                    $customProperties = $this->getObjectCustomProperties($directorHostObj);
                } else {
                    $customProperties = $this->getObjectCustomProperties($directorServiceObj);
                }

                if (empty($customProperties)) {
                    return false;
                }
            }

            $fieldsWithDataLists = [];
            foreach ($fields as $field) {
                $this->fieldConfig[$field->get('varname')] = [
                    'label'      => $field->get('caption'),
                    'group'      => $field->getCategoryName(),
                    'visibility' => $field->getSetting('visibility')
                ];

                if ($field->get('datatype') === 'Icinga\Module\Director\DataType\DataTypeDatalist') {
                    $fieldsWithDataLists[$field->get('id')] = $field;
                } elseif ($field->get('datatype') === 'Icinga\Module\Director\DataType\DataTypeDictionary') {
                    $this->dictionaryNames[] = $field->get('varname');
                }
            }

            if (! empty($fieldsWithDataLists)) {
                if ($this->db()->getDbType() === 'pgsql') {
                    $joinCondition = 'CAST(dds.setting_value AS INTEGER) = dde.list_id';
                } else {
                    $joinCondition = 'dds.setting_value = dde.list_id';
                }

                $dataListEntries = $db->select()->from(
                    ['dds' => 'director_datafield_setting'],
                    [
                        'dds.datafield_id',
                        'dde.entry_name',
                        'dde.entry_value'
                    ]
                )->join(
                    ['dde' => 'director_datalist_entry'],
                    $joinCondition,
                    []
                )->where('dds.datafield_id', array_keys($fieldsWithDataLists))
                    ->where('dds.setting_name', 'datalist_id');

                foreach ($dataListEntries as $dataListEntry) {
                    $field = $fieldsWithDataLists[$dataListEntry->datafield_id];
                    $this->datalistMaps[$field->get('varname')][$dataListEntry->entry_name]
                        = $dataListEntry->entry_value;
                }
            }

            $customPropertiesWithDatalists = [];
            foreach ($customProperties as $customProperty) {
                $this->customPropertyConfig[$customProperty['key_name']] = ['label' => $customProperty['label']];

                if (str_starts_with($customProperty['value_type'], 'datalist-')) {
                    $customPropertiesWithDatalists[$customProperty['uuid']] = $customProperty;
                } elseif (str_starts_with($customProperty['value_type'], 'dictionary-')) {
                    $this->dictionaryNames[] = $customProperty['key_name'];
                }
            }

            if (! empty($customPropertiesWithDatalists)) {
                $dataListEntries = $db->select()->from(
                    ['dpd' => 'director_property_datalist'],
                    [
                        'property_uuid' => 'dpd.property_uuid',
                        'entry_name' => 'dde.entry_name',
                        'entry_value' => 'dde.entry_value'
                    ]
                )->join(
                    ['dd' => 'director_datalist'],
                    'dd.uuid = dpd.list_uuid',
                    []
                )->join(
                    ['dde' => 'director_datalist_entry'],
                    'dd.id = dde.list_id',
                    []
                )->where('dpd.property_uuid', array_keys($customPropertiesWithDatalists));

                foreach ($dataListEntries as $dataListEntry) {
                    $customProperty = $customPropertiesWithDatalists[$dataListEntry->property_uuid];
                    $this->datalistMaps[$customProperty['key_name']][$dataListEntry->entry_name]
                        = $dataListEntry->entry_value;
                }
            }

            return true;
        } catch (Throwable $e) {
            Logger::error("%s\n%s", $e, $e->getTraceAsString());

            return false;
        }
    }

    /**
     * Get custom properties for the host.
     *
     * @return array
     */
    protected function getObjectCustomProperties(IcingaObject $object, bool $isOverrideVars = false): array
    {
        if ($object->uuid === null) {
            return [];
        }

        $type = $object->getShortTableName();
        $parents = $object->listAncestorIds();

        $uuids = [];
        $db = $this->db();

        $objectClass = DbObjectTypeRegistry::classByType($type);
        foreach ($parents as $parent) {
            $uuids[] = $objectClass::loadWithAutoIncId($parent, $db)->get('uuid');
        }

        $uuids[] = $object->get('uuid');
        $query = $db->getDbAdapter()
            ->select()
            ->from(
                ['dp' => 'director_property'],
                [
                    'key_name' => 'dp.key_name',
                    'uuid' => 'dp.uuid',
                    $type . '_uuid' => 'iop.' . $type . '_uuid',
                    'value_type' => 'dp.value_type',
                    'label' => 'dp.label',
                    'children' => 'COUNT(cdp.uuid)'
                ]
            )
            ->join(['iop' => "icinga_$type" . '_property'], 'dp.uuid = iop.property_uuid', [])
            ->joinLeft(['cdp' => 'director_property'], 'cdp.parent_uuid = dp.uuid', [])
            ->where('iop.' . $type . '_uuid IN (?)', $uuids)
            ->group(['dp.uuid', 'dp.key_name', 'dp.value_type', 'dp.label'])
            ->order(
                "FIELD(dp.value_type, 'string', 'number', 'bool', 'datalist-strict', 'datalist-non-strict',"
                . " 'dynamic-array',  'fixed-dictionary', 'dynamic-dictionary')"
            )
            ->order('children')
            ->order('key_name');

        $result = [];

        foreach ($db->getDbAdapter()->fetchAll($query, fetchMode: PDO::FETCH_ASSOC) as $row) {
            $result[$row['key_name']] = $row;
        }

        return $result;
    }

    public function renderCustomVarKey(string $key)
    {
        try {
            $label = $this->fieldConfig[$key]['label']
                ?? $this->customPropertyConfig[$key]['label']
                ?? null;
            if ($label === null) {
                return null;
            }

            return new HtmlElement(
                'span',
                Attributes::create(['title' => $label . " [$key]"]),
                Text::create($label)
            );
        } catch (Throwable $e) {
            Logger::error("%s\n%s", $e, $e->getTraceAsString());
        }

        return null;
    }

    public function renderCustomVarValue(string $key, $value)
    {
        try {
            if (isset($this->fieldConfig[$key]) || isset($this->customPropertyConfig[$key])) {
                if (isset($this->fieldConfig[$key]) && $this->fieldConfig[$key]['visibility'] === 'hidden') {
                    return '***';
                }

                if (is_array($value)) {
                    $renderedValue = [];
                    foreach ($value as $v) {
                        if (is_string($v) && isset($this->datalistMaps[$key][$v])) {
                            $renderedValue[] = new HtmlElement(
                                'span',
                                Attributes::create(['title' => $this->datalistMaps[$key][$v] . " [$v]"]),
                                Text::create($this->datalistMaps[$key][$v])
                            );
                        } else {
                            $renderedValue[] = $v;
                        }
                    }

                    return $renderedValue;
                }

                if (is_string($value) && isset($this->datalistMaps[$key][$value])) {
                    return new HtmlElement(
                        'span',
                        Attributes::create(['title' => $this->datalistMaps[$key][$value] . " [$value]"]),
                        Text::create($this->datalistMaps[$key][$value])
                    );
                } elseif ($value !== null && in_array($key, $this->dictionaryNames)) {
                    return $this->renderDictionaryVal($key, (array) $value);
                }
            }
        } catch (Throwable $e) {
            Logger::error("%s\n%s", $e, $e->getTraceAsString());
        }

        return null;
    }

    public function identifyCustomVarGroup(string $key): ?string
    {
        if (isset($this->fieldConfig[$key]['group'])) {
            return $this->fieldConfig[$key]['group'];
        }

        return null;
    }

    /**
     * Render the dictionary value
     *
     * @param string $key
     * @param array $value
     *
     * @return ?ValidHtml
     */
    protected function renderDictionaryVal(string $key, array $value): ?ValidHtml
    {
        if ($this->dictionaryLevel > 0) {
            $numItems = count($value);
            $label = $this->renderCustomVarKey($key) ?? $key;

            $this->dictionaryBody->addHtml(
                new HtmlElement(
                    'tr',
                    Attributes::create(['class' => "level-{$this->dictionaryLevel}"]),
                    new HtmlElement('th', null, Html::wantHtml($label)),
                    new HtmlElement(
                        'td',
                        null,
                        Text::create(sprintf(tp('%d item', '%d items', $numItems), $numItems))
                    )
                )
            );
        } else {
            $this->dictionaryTable = new HtmlElement(
                'table',
                Attributes::create(['class' => ['custom-var-table', 'name-value-table']])
            );

            $this->dictionaryBody = new HtmlElement('tbody');
        }

        $this->dictionaryLevel++;

        foreach ($value as $key => $val) {
            if ($key !== null && is_array($val) || is_object($val)) {
                $val = (array) $val;
                $numChildItems = count($val);

                $this->dictionaryBody->addHtml(
                    new HtmlElement(
                        'tr',
                        Attributes::create(['class' => "level-{$this->dictionaryLevel}"]),
                        new HtmlElement('th', null, Html::wantHtml($key)),
                        new HtmlElement(
                            'td',
                            null,
                            Text::create(sprintf(tp('%d item', '%d items', $numChildItems), $numChildItems))
                        )
                    )
                );

                $this->dictionaryLevel++;
                foreach ($val as $childKey => $childVal) {
                    $childVal = $this->renderCustomVarValue($childKey, $childVal) ?? $childVal;
                    if (! in_array($childKey, $this->dictionaryNames)) {
                        $label = $this->renderCustomVarKey($childKey) ?? $childKey;

                        if (is_array($childVal)) {
                            $this->renderArrayVal($label, $childVal);
                        } else {
                            $this->dictionaryBody->addHtml(
                                new HtmlElement(
                                    'tr',
                                    Attributes::create(['class' => "level-{$this->dictionaryLevel}"]),
                                    new HtmlElement('th', null, Html::wantHtml(
                                        $label
                                    )),
                                    new HtmlElement('td', null, Html::wantHtml($childVal))
                                )
                            );
                        }
                    }
                }

                $this->dictionaryLevel--;
            } elseif (is_array($val)) {
                $this->renderArrayVal($key, $val);
            } else {
                $this->dictionaryBody->addHtml(
                    new HtmlElement(
                        'tr',
                        Attributes::create(['class' => "level-{$this->dictionaryLevel}"]),
                        new HtmlElement('th', null, Html::wantHtml($key)),
                        new HtmlElement('td', null, Html::wantHtml($val))
                    )
                );
            }
        }

        $this->dictionaryLevel--;

        if ($this->dictionaryLevel === 0) {
            return $this->dictionaryTable->addHtml($this->dictionaryBody);
        }

        return null;
    }

    /**
     * Render an array
     *
     * @param HtmlElement|string $name
     * @param array $array
     *
     * @return void
     */
    protected function renderArrayVal($name, array $array)
    {
        $numItems = count($array);

        if ($name instanceof HtmlElement) {
            $name->addHtml(Text::create(' (Array)'));
        } else {
            $name = (new HtmlDocument())->addHtml(
                Html::wantHtml($name),
                Text::create(' (Array)')
            );
        }

        $this->dictionaryBody->addHtml(
            new HtmlElement(
                'tr',
                Attributes::create(['class' => "level-{$this->dictionaryLevel}"]),
                new HtmlElement('th', null, Html::wantHtml($name)),
                new HtmlElement('td', null, Html::wantHtml(sprintf(tp('%d item', '%d items', $numItems), $numItems)))
            )
        );

        ++$this->dictionaryLevel;

        ksort($array);
        foreach ($array as $key => $value) {
            $this->dictionaryBody->addHtml(
                new HtmlElement(
                    'tr',
                    Attributes::create(['class' => "level-{$this->dictionaryLevel}"]),
                    new HtmlElement('th', null, Html::wantHtml("[$key]")),
                    new HtmlElement('td', null, Html::wantHtml($value))
                )
            );
        }

        --$this->dictionaryLevel;
    }
}
