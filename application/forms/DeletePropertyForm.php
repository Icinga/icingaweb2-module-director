<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Web\Widget\CustomVarObjectList;
use Icinga\Web\Session;
use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\ListItem;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Zend_Db;
use Zend_Db_Expr;

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

        $objectClasses = ['host', 'service', 'notification', 'command', 'user'];
        $usage = [];

        foreach ($objectClasses as $objectClass) {
            $customPropertyQuery = $db
                ->select()
                ->from(['io' => "icinga_$objectClass"], [])
                ->join(['iov' => "icinga_$objectClass" .'_var'], "io.id = iov.$objectClass" . '_id', [])
                ->join(['dp' => 'director_property'], 'iov.property_uuid = dp.uuid', []);

            $unionQuery = $db
                ->select()
                ->from(['io' => "icinga_$objectClass"], [])
                ->join(['iop' => "icinga_$objectClass" . '_property'], "iop.$objectClass" . '_uuid = io.uuid', [])
                ->join(['dp' => 'director_property'], 'iop.property_uuid = dp.uuid', []);

            $columns = [
                'name' => 'io.object_name',
                'object_class' => new Zend_Db_Expr("'$objectClass'"),
                'type' => 'io.object_type'
            ];

            if ($objectClass === 'service') {
                $customPropertyQuery = $customPropertyQuery->joinLeft(['ioh' => 'icinga_host'], 'io.host_id = ioh.id', []);
                $unionQuery = $unionQuery->joinLeft(['ioh' => 'icinga_host'], 'io.host_id = ioh.id', []);
                $columns['host_name'] = 'ioh.object_name';
            }

            $customPropertyQuery = $customPropertyQuery->columns($columns)
                                                       ->where('dp.uuid = ?', Uuid::fromBytes($uuid)->getBytes());


            $unionQuery = $unionQuery->columns($columns)
                                     ->where('dp.uuid = ?', $uuid);

            $usage[] = $db->fetchAll($db->select()->union([$customPropertyQuery, $unionQuery]));
        }

        return array_merge(...$usage);
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

        $objectClass = null;
        $usageList = (new CustomVarObjectList($customVarUsage))
            ->on(
                CustomVarObjectList::BEFORE_ITEM_ADD,
                function (ListItem $item, $data) use(&$objectClass, &$usageList) {
                    if ($objectClass !== $data->object_class) {
                        $usageList->addHtml(HtmlElement::create(
                            'li',
                            ['class' => 'list-item'],
                            HtmlElement::create(
                                'h2',
                                content: ucfirst($data->object_class) . 's'
                            )
                        ));
                        $objectClass = $data->object_class;
                    }
                });

        $this->addHtml($usageList);

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
        $db = $this->db;

        $db->getDbAdapter()->beginTransaction();
        $prop = $this->property;

        if (str_starts_with($prop['value_type'], 'datalist-')) {
            $db->delete('director_property_datalist', Filter::where('property_uuid', $uuid->getBytes()));
        }

        $db->delete('director_property', Filter::where('uuid', $uuid->getBytes()));
        $db->delete('director_property', Filter::where('parent_uuid', $uuid->getBytes()));
        $this->removeObjectCustomVars($prop, $this->parent);

        $objects = ['host', 'service', 'notification', 'command', 'user'];
        foreach ($objects as $object) {
            $this->db->delete("icinga_{$object}_var", Filter::where('property_uuid', $uuid->getBytes()));
        }

        $db->getDbAdapter()->commit();
    }

    private function removeObjectCustomVars(array $property, ?array $parent = null): void
    {
        $objectTypes = ['host', 'service', 'notification', 'command', 'user'];
        $db = $this->db->getDbAdapter();
        if ($parent) {
            if ($parent['parent_uuid'] !== null) {
                // If the parent has in turn a parent
                $rootUuid = Uuid::fromBytes($parent['parent_uuid']);
                $rootProp = $this->fetchProperty($rootUuid);
                $rootType = $rootProp['value_type'];
            } else {
                $rootType = $parent['value_type'];
                $rootUuid = Uuid::fromBytes($parent['uuid']);
            }

            foreach ($objectTypes as $objectType) {
                $query = $db
                    ->select()
                    ->from(['iov' => "icinga_{$objectType}_var"], [])
                    ->columns([
                        "{$objectType}_id",
                        'varname',
                        'varvalue',
                    ])
                    ->where('property_uuid = ?', $rootUuid->getBytes());

                $customVars = $db->fetchAll($query, [], Zend_Db::FETCH_ASSOC);

                foreach ($customVars as $customVar) {
                    $class = DbObjectTypeRegistry::classByType($objectType);
                    $object = $class::loadWithAutoIncId($customVar["{$objectType}_id"], $this->db);
                    $varName = $customVar['varname'];
                    $varValue = json_decode($customVar['varvalue'], true);
                    if ($rootType === 'dynamic-dictionary') {
                        foreach ($varValue as $key => $value) {
                            if ($parent['parent_uuid'] === null) {
                                $this->removeDictionaryItem($value, [$property['key_name']]);
                            } else {
                                $this->removeDictionaryItem(
                                    $value,
                                    [$parent['key_name'], $property['key_name']]
                                );
                            }

                            $varValue[$key] = (object) $value;
                        }
                    } else {
                        if ($parent['parent_uuid'] === null) {
                            $this->removeDictionaryItem($varValue, [$property['key_name']]);
                        } else {
                            $this->removeDictionaryItem(
                                $varValue,
                                [$parent['key_name'], $property['key_name']]
                            );
                        }
                    }

                    $objectVars = $object->vars();
                    if (empty($varValue)) {
                        $objectVars->set($varName, null);
                    } else {
                        if ($parent && $parent['value_type'] === 'fixed-array') {
                            $this->updateFixedArrayItems(Uuid::fromBytes($parent['uuid']));
                            $varValue[$parent['key_name']] = array_values($varValue[$parent['key_name']]);
                        } elseif ($rootType === 'fixed-array') {
                            $this->updateFixedArrayItems($rootUuid);
                            $varValue = array_values($varValue);
                        }

                        $objectVars->set($varName, $varValue);
                        $objectVars->storeToDb($object);
                    }
                }
            }
        }
    }

    /**
     * Update the items for the given fixed array
     *
     * @param UuidInterface $uuid
     *
     * @return void
     */
    private function updateFixedArrayItems(UuidInterface $uuid): void
    {
        $db = $this->db->getDbAdapter();
        $query = $db
            ->select()
            ->from(['dp' => 'director_property'], [])
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
