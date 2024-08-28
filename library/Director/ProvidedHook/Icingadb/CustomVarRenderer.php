<?php

namespace Icinga\Module\Director\ProvidedHook\Icingadb;

use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Form\IcingaObjectFieldLoader;
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

class CustomVarRenderer extends CustomVarRendererHook
{
    /** @var array Related datafield configuration */
    protected $fieldConfig = [];

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
            if ($service !== null) {
                $directorServiceObj = IcingaService::load([
                    'host_id'     => $directorHostObj->get('id'),
                    'object_name' => $service->name
                ], $db);
            }
        } catch (NotFoundError $_) {
            return false;
        }

        if ($service === null) {
            $fields = (new IcingaObjectFieldLoader($directorHostObj))->getFields();
        } else {
            $fields = (new IcingaObjectFieldLoader($directorServiceObj))->getFields();
        }

        if (empty($fields)) {
            return false;
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
                $this->datalistMaps[$field->get('varname')][$dataListEntry->entry_name] = $dataListEntry->entry_value;
            }
        }

        return true;
    }

    public function renderCustomVarKey(string $key)
    {
        if (isset($this->fieldConfig[$key]['label'])) {
            return new HtmlElement(
                'span',
                Attributes::create(['title' => $this->fieldConfig[$key]['label'] . " [$key]"]),
                Text::create($this->fieldConfig[$key]['label'])
            );
        }

        return null;
    }

    public function renderCustomVarValue(string $key, $value)
    {
        if (isset($this->fieldConfig[$key])) {
            if ($this->fieldConfig[$key]['visibility'] === 'hidden') {
                return '***';
            }

            if (isset($this->datalistMaps[$key][$value])) {
                return new HtmlElement(
                    'span',
                    Attributes::create(['title' => $this->datalistMaps[$key][$value] . " [$value]"]),
                    Text::create($this->datalistMaps[$key][$value])
                );
            } elseif ($value !== null && in_array($key, $this->dictionaryNames)) {
                return $this->renderDictionaryVal($key, (array) $value);
            }
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
