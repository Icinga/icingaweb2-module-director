<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Web\Widget\CustomVarObjectList;
use Icinga\Web\Session;
use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use ipl\Web\Widget\Icon;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Zend_Db;

class DeletePropertyForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    /** @var bool Whether to hide the key name element or not (checked for the fixed array) */
    private $hideKeyNameElement = false;

    /** @var bool Whether the field is a nested field or not */
    private $isNestedField = false;

    public function __construct(
        protected DbConnection $db,
        protected array $property,
        protected array $parent = []
    ) {
    }

    /**
     * Fetch the give custom variable usage in templates
     *
     * @return array
     */
    private function fetchCustomVarUsage(): array
    {
        $db = $this->db->getDbAdapter();
        if ($this->parent) {
            if ($this->parent['parent_uuid'] !== null) {
                $uuid = $this->parent['parent_uuid'];
            } else {
                $uuid = $this->parent['uuid'];
            }
        } else {
            $uuid = $this->property['uuid'];
        }

        $customPropQuery = $db
            ->select()
            ->from(['ih' => 'icinga_host'], [])
            ->join(['ihv' => 'icinga_host_var'], 'ih.id = ihv.host_id', [])
            ->join(['dp' => 'director_property'], 'ihv.property_uuid = dp.uuid', [])
            ->columns([
                'name' => 'ih.object_name',
                'type' => 'ih.object_type'
            ])
            ->where('dp.uuid = ?', Uuid::fromBytes($uuid)->getBytes());

        $unionQuery = $db
            ->select()
            ->from(['ih' => 'icinga_host'], [])
            ->join(['ihp' => 'icinga_host_property'], 'ihp.host_uuid = ih.uuid', [])
            ->join(['dp' => 'director_property'], 'ihp.property_uuid = dp.uuid', [])
            ->columns([
                'name' => 'ih.object_name',
                'type' => 'ih.object_type'
            ])
            ->where('dp.uuid = ?', $uuid);

        return $db->fetchAll($db->select()->union([$customPropQuery, $unionQuery]));
    }

    protected function assemble(): void
    {
        $customVarUsage = $this->fetchCustomVarUsage();
        if (count($customVarUsage) > 0) {
            if ($this->parent) {
                if ($this->parent['parent_uuid'] !== null) {
                    $info = sprintf($this->translate(
                        'Deleting this sub field from custom property "%s" will remove this field in'
                        . ' the corresponding custom variables from the below templates and objects.'
                        . ' Are you sure you want to delete it?'
                    ), $this->fetchProperty(Uuid::fromBytes($this->parent['parent_uuid']))['key_name']);
                } else {
                    $info = sprintf($this->translate(
                        'Deleting this field from custom property "%s" will remove this field in'
                        . ' the corresponding custom variables from the below templates and objects.'
                        . ' Are you sure you want to delete it?'
                    ), $this->parent['key_name']);
                }
            } else {
                $info = $this->translate(
                    'Deleting this custom property will remove the corresponding custom variable'
                    . ' from the below templates and objects. Are you sure you want to delete it?'
                );
            }
        } else {
            if ($this->parent) {
                $info = $this->translate('The field is not in use and hence can be safely deleted.');
            } else {
                $info = $this->translate('The custom property is not in use and hence can be safely deleted.');
            }
        }

        $this->addHtml(new HtmlElement(
            'div',
            Attributes::create(['class' => 'form-description']),
            new Icon('info-circle', ['class' => 'form-description-icon']),
            new HtmlElement(
                'ul',
                null,
                new HtmlElement('li', null, Text::create($info))
            )
        ));

        $this->addHtml(new CustomVarObjectList($customVarUsage));

        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Delete'),
            'class' => 'btn-remove'
        ]);
    }

    /**
     * Fetch property for the given UUID
     *
     * @param UuidInterface $uuid UUID of the given property
     *
     * @return array<string, mixed>
     */
    private function fetchProperty(UuidInterface $uuid): array
    {
        $db = $this->db->getDbAdapter();

        $query = $db
            ->select()
            ->from(['dp' => 'director_property'], [])
            ->joinLeft(['ihp' => 'icinga_host_property'], 'ihp.property_uuid = dp.uuid', [])
            ->columns([
                'key_name',
                'uuid',
                'parent_uuid',
                'value_type',
                'label',
                'description'
            ])
            ->where('uuid = ?', $uuid->getBytes());

        return $db->fetchRow($query, [], Zend_Db::FETCH_ASSOC);
    }

    /**
     * Fetch property for the given UUID
     *
     * @param UuidInterface $uuid UUID of the given property
     *
     * @return array<string, mixed>
     */
    private function fetchCustomVars(UuidInterface $uuid): array
    {
        $db = $this->db->getDbAdapter();

        $query = $db
            ->select()
            ->from(['ihv' => 'icinga_host_var'], [])
            ->columns([
                'host_id',
                'varname',
                'varvalue',
                'property_uuid'
            ])
            ->where('property_uuid = ?', $uuid->getBytes());

        return $db->fetchAll($query, [], Zend_Db::FETCH_ASSOC);
    }

    /**
     * Remove dictionary item from the give data array
     *
     * @param array $item
     * @param array $path
     *
     * @return void
     */
    private function removeDictionaryItem(array &$item, array $path): void
    {
        $key = array_shift($path);

        if (! array_key_exists($key, $item)) {
            return;
        }

        if (empty($path)) {
            unset($item[$key]);
        } elseif (is_array($item[$key])) {
            $this->removeDictionaryItem($item[$key], $path);
        }

        // Remove empty array items
        if (isset($item[$key]) && empty($item[$key])) {
            unset($item[$key]);
        }
    }

    protected function onSuccess(): void
    {
        $uuid = Uuid::fromBytes($this->property['uuid']);
        $prop = $this->fetchProperty($uuid);

        $this->db->getDbAdapter()->beginTransaction();
        $this->db->delete('director_property', Filter::where('uuid', $uuid->getBytes()));
        $this->db->delete('director_property', Filter::where('parent_uuid', $uuid->getBytes()));

        if ($this->parent) {
            if ($this->parent['parent_uuid'] !== null) {
                // If the parent has in turn a parent
                $rootUuid = Uuid::fromBytes($this->parent['parent_uuid']);
                $rootProp = $this->fetchProperty($rootUuid);
                $rootType = $rootProp['value_type'];
            } else {
                $rootType = $this->parent['value_type'];
                $rootUuid = Uuid::fromBytes($this->parent['uuid']);
            }

            $customVars = $this->fetchCustomVars($rootUuid);

            foreach ($customVars as $customVar) {
                $varValue = json_decode($customVar['varvalue'], true);
                if ($rootType === 'dynamic-dictionary') {
                    foreach ($varValue as $key => $value) {
                        if ($this->parent['parent_uuid'] === null) {
                            $this->removeDictionaryItem($value, [$prop['key_name']]);
                        } else {
                            $this->removeDictionaryItem(
                                $value,
                                [$this->parent['key_name'], $prop['key_name']]
                            );
                        }

                        $varValue[$key] = $value;
                    }
                } else {
                    if ($this->parent['parent_uuid'] === null) {
                        $this->removeDictionaryItem($varValue, [$prop['key_name']]);
                    } else {
                        $this->removeDictionaryItem(
                            $varValue,
                            [$this->parent['key_name'], $prop['key_name']]
                        );
                    }
                }

                if (empty($varValue)) {
                    $this->db->delete(
                        'icinga_host_var',
                        Filter::matchAll(
                            Filter::where('property_uuid', $rootUuid->getBytes()),
                            Filter::where('host_id', $customVar['host_id'])
                        )
                    );
                } else {
                    if ($this->parent && $this->parent['value_type'] === 'fixed-array') {
                        $this->updateFixedArrayItems(Uuid::fromBytes($this->parent['uuid']));
                        $varValue[$this->parent['key_name']] = array_values($varValue[$this->parent['key_name']]);
                    } elseif ($rootType === 'fixed-array') {
                        $this->updateFixedArrayItems($rootUuid);
                        $varValue = array_values($varValue);
                    }

                    $this->db->update(
                        'icinga_host_var',
                        ['varvalue' => json_encode($varValue)],
                        Filter::matchAll(
                            Filter::where('property_uuid', $rootUuid->getBytes()),
                            Filter::where('host_id', $customVar['host_id'])
                        )
                    );
                }
            }
        }

        $this->db->delete('icinga_host_var', Filter::where('property_uuid', $uuid->getBytes()));
        $this->db->getDbAdapter()->commit();
    }

    private function updateFixedArrayItems(UuidInterface $uuid): void
    {
        $db = $this->db->getDbAdapter();
        $query = $db
            ->select()
            ->from(['dp' => 'director_property'], [])
            ->joinLeft(['ihp' => 'icinga_host_property'], 'ihp.property_uuid = dp.uuid', [])
            ->columns([
                'key_name',
                'uuid',
                'parent_uuid',
                'value_type',
                'label',
                'description'
            ])
            ->where('parent_uuid = ?', $uuid->getBytes());

        $propItems = $db->fetchAll($query, [], Zend_Db::FETCH_ASSOC);

        $db->delete(
            'director_property',
            ['parent_uuid = ?' => $uuid->getBytes()]
        );

        $count = 0;
        foreach ($propItems as $propItem) {
            $this->db->insert('director_property', [
                'uuid' => Uuid::fromBytes($propItem['uuid'])->getBytes(),
                'parent_uuid' => $uuid->getBytes(),
                'key_name' => $count,
                'label' => $propItem['label'],
                'value_type' => $propItem['value_type'],
                'description' => $propItem['description']
            ]);

            $count++;
        }
    }
}
