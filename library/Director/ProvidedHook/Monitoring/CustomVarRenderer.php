<?php

namespace Icinga\Module\Director\ProvidedHook\Monitoring;

use Icinga\Application\Config;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Form\IcingaObjectFieldLoader;
use Icinga\Module\Monitoring\Hook\CustomVarRendererHook;
use Icinga\Module\Monitoring\Object\Host;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Service;
use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use ipl\Html\Text;

class CustomVarRenderer extends CustomVarRendererHook
{
    /** @var array Related datafield configuration */
    protected $fieldConfig = [];

    /** @var array Related datalists and their keys and values */
    protected $datalistMaps = [];

    /**
     * Get a database connection to the director database
     *
     * @return Db
     *
     * @throws ConfigurationError
     */
    protected function db()
    {
        $resourceName = Config::module('director')->get('db', 'resource');
        if (! $resourceName) {
            throw new ConfigurationError('Cannot identify director db. No resource configured');
        }

        return Db::fromResourceName($resourceName);
    }

    public function prefetchForObject(MonitoredObject $object)
    {
        if ($object instanceof Host) {
            $host = $object;
            $service = null;
        } elseif ($object instanceof Service) {
            $host = $object->getHost();
            $service = $object;
        } else {
            return false;
        }

        $db = $this->db();

        try {
            $directorHostObj = IcingaHost::load($host->getName(), $db);
            if ($service !== null) {
                $directorServiceObj = IcingaService::load([
                    'host_id'     => $directorHostObj->get('id'),
                    'object_name' => $service->getName()
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
            }
        }

        if (! empty($fieldsWithDataLists)) {
            $dataListEntries = $db->select()->from(
                ['dds' => 'director_datafield_setting'],
                [
                    'dds.datafield_id',
                    'dde.entry_name',
                    'dde.entry_value'
                ]
            )->join(
                ['dde' => 'director_datalist_entry'],
                'dds.setting_value = dde.list_id',
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

    public function renderCustomVarKey($key)
    {
        if (isset($this->fieldConfig[$key]['label'])) {
            return new HtmlElement(
                'span',
                Attributes::create(['title' => $this->fieldConfig[$key]['label'] . " [$key]"]),
                Text::create($this->fieldConfig[$key]['label'])
            );
        }
    }

    public function renderCustomVarValue($key, $value)
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
            }
        }
    }

    public function identifyCustomVarGroup($key)
    {
        if (isset($this->fieldConfig[$key]['group'])) {
            return $this->fieldConfig[$key]['group'];
        }
    }
}
