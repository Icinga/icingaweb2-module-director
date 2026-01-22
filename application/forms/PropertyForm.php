<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Web\Form;
use Icinga\Web\Session;
use ipl\Html\FormDecoration\FormElementDecorationResult;
use ipl\Html\HtmlElement;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use ipl\Web\Compat\FormDecorator\DescriptionDecorator;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;
use PDO;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use stdClass;
use Throwable;
use Zend_Db;

class PropertyForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    /** @var bool Whether to hide the key name element or not (checked for the fixed array) */
    private $hideKeyNameElement = false;

    /** @var bool Whether the field is a nested field or not */
    private $isNestedField = false;

    public function __construct(
        protected DbConnection $db,
        protected ?UuidInterface $uuid = null,
        protected bool $field = false,
        protected ?UuidInterface $parentUuid = null
    ) {
        $this->getAttributes()->add(['class' => ['property-form']]);
    }

    /**
     * Get the UUID of the property
     *
     * @return ?UuidInterface
     */
    public function getUUid(): ?UuidInterface
    {
        return $this->uuid;
    }

    /**
     * Get UUID of the parent property
     *
     * @return ?UuidInterface
     */
    public function getParentUUid(): ?UuidInterface
    {
        return $this->parentUuid;
    }

    /**
     * Set whether to hide the key name element or not (checked for the fixed array)
     *
     * @param bool $hideKeyNameElement
     *
     * @return $this
     */
    public function setHideKeyNameElement(bool $hideKeyNameElement): self
    {
        $this->hideKeyNameElement = $hideKeyNameElement;

        return $this;
    }

    /**
     * Set whether the field is a nested field (field in a sub dictionary) or not
     *
     * @param bool $isNestedField
     *
     * @return $this
     */
    public function setIsNestedField(bool $isNestedField): self
    {
        $this->isNestedField = $isNestedField;

        return $this;
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $this->addElement('hidden', 'used_count', ['ignore' => true]);
        $used = (int) $this->getValue('used_count') > 0;

        if ($this->hideKeyNameElement) {
            $db = $this->db->getDbAdapter();
            $query = $db->select()
                ->from('director_property', ['count' => 'COUNT(*)'])
                ->where('parent_uuid = ?', $this->parentUuid->getBytes());

            $this->addElement(
                'hidden',
                'key_name',
                [
                    'label'     => $this->translate('Property Key *'),
                    'required'  => true,
                    'value'     => $db->fetchOne($query)
                ]
            );
        } else {
            $this->addElement(
                'text',
                'key_name',
                [
                    'label'     => $this->translate('Property Key *'),
                    'required'  => true
                ]
            );
        }

        $this->addElement(
            'text',
            'label',
            [
                'label'     => $this->translate('Property Label'),
                'required'  => $this->hideKeyNameElement
            ]
        );

        $this->addElement(
            'textarea',
            'description',
            ['label' => $this->translate('Property Description')]
        );

        $types = [
            'string' => 'String',
            'number' => 'Number',
            'bool' => 'Boolean',
            'datalist-strict' => 'Data List Strict',
            'datalist-non-strict' => 'Data List Non Strict',
        ];

        if (! $this->isNestedField) {
            $types += [
                'fixed-array' => 'Fixed Array',
                'dynamic-array' => 'Dynamic Array',
                'fixed-dictionary' => 'Fixed Dictionary'
            ];

            if ($this->parentUuid === null) {
                $types += [
                    'dynamic-dictionary' => 'Dynamic Dictionary'
                ];
            }
        }

        $this->addElement(
            'select',
            'value_type',
            [
                'label'             => $this->translate('Property Type *'),
                'class'             => 'autosubmit',
                'required'          => true,
                'disabledOptions'   => [''],
                'value'             => 'string',
                'options'           => $types
            ]
        );

        if ($used) {
            $this->getElement('value_type')
                 ->setAttribute(
                     'title',
                     $this->translate(
                         'This property is used in one or more templates and hence the value type cannot be changed.'
                     )
                 )
                 ->setAttribute('disabled', true);
        }

        $type = $this->getValue('value_type');
        if ($type === 'dynamic-array') {
            $this->addElement(
                'select',
                'item_type',
                [
                    'label'             => $this->translate('Item Type'),
                    'class'             => 'autosubmit',
                    'disabledOptions'   => [''],
                    'value'             => 'string',
                    'options'           => array_slice($types, 0, 2)
                ]
            );

            if ($used) {
                $this->getElement('item_type')
                    ->setAttribute(
                        'title',
                        $this->translate(
                            'This property is used in one or more templates and hence the item type cannot be changed.'
                        )
                    )
                    ->setAttribute('disabled', true);
            }
        } elseif (str_starts_with($type, 'datalist')) {
            $isStrict = substr_compare($type, 'strict', strlen('datalist-')) === 0;
            $this->getElement('value_type')->setAttribute('strict', $isStrict);
            $this->addElement(
                'select',
                'list',
                [
                    'label'             => $this->translate('List name'),
                    'class'             => 'autosubmit',
                    'disabledOptions'   => [''],
                    'value'             => '',
                    'required'          => true,
                    'options'           => ['' => $this->translate('- please choose -')] + $this->enumDatalist()
                ]
            );

            $this->addElement(
                'select',
                'item_type',
                [
                    'label'             => $this->translate('Item Type'),
                    'class'             => 'autosubmit',
                    'disabledOptions'   => [''],
                    'value'             => 'string',
                    'options'           => ['string' => 'String', 'dynamic-array' => 'Array']
                ]
            );

            if ($used) {
                $this->getElement('item_type')
                     ->setAttribute(
                         'title',
                         $this->translate(
                             'This property is used in one or more templates and hence the item type cannot be changed.'
                         )
                     )
                     ->setAttribute('disabled', true);
            }
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->uuid ? $this->translate('Save') : $this->translate('Add')
        ]);

        if ($this->uuid) {
            // TODO: Ask for confirmation before deleting
            $this->getElement('submit')
                 ->getWrapper()
                ->prepend(
                    (new ButtonLink(
                        $this->translate('Delete'),
                        Url::fromPath(
                            'director/property/delete',
                            ['uuid' => $this->uuid->toString()]
                        ),
                        null,
                        ['class' => ['btn-remove']]
                    ))->openInModal()
                );
        }
    }

    private function enumDatalist(): array
    {
        return $this->db->fetchPairs(
            $this->db->select()->from('director_datalist', ['id', 'list_name'])->order('list_name')
        );
    }

    private function fetchDatalist(int $id): array
    {
        return (array) $this->db->fetchRow(
            $this->db->select()->from('director_datalist', ['*'])
                ->where('id', $id)
        );
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

    private function updateObjectCustomVars(array $path, array $newPath, array &$item): void
    {
        $key = array_shift($path);
        $newKey = array_shift($newPath);

        if (! array_key_exists($key, $item)) {
            return;
        }

        if (empty($path) && empty($newPath) && $key !== $newKey) {
            $item[$newKey] = $item[$key];
            unset($item[$key]);
        } elseif (is_array($item[$key])) {
            $this->updateObjectCustomVars($path, $newPath, $item[$key]);
        }

        // Remove empty array items
        if (isset($item[$key]) && empty($item[$key])) {
            unset($item[$key]);
        }
    }

    protected function onSuccess(): void
    {
        $values = $this->getValues();
        $datalist = '';
        $itemType = '';
        $valueType = $values['value_type'];
        if (str_starts_with($valueType, 'datalist-')) {
            $datalist = $this->fetchDatalist($values['list']);
            $itemType = $values['item_type'];
            unset($values['list']);
        } elseif ($valueType == 'dynamic-array') {
            $itemType = $values['item_type'];
        }

        if (isset($values['list'])) {
            unset($values['list']);
        }

        if (isset($values['item_type'])) {
            unset($values['item_type']);
        }

        if ($this->uuid === null) {
            $this->uuid = Uuid::uuid4();
            $dynamicArrayItemType = [];
            if ($itemType !== '') {
                $dynamicArrayItemType = [
                    'uuid' => Uuid::uuid4()->getBytes(),
                    'key_name' => '0',
                    'value_type' => $itemType,
                    'parent_uuid' => $this->uuid->getBytes()
                ];
            }

            if ($this->field) {
                $values = array_merge(
                    [
                        'uuid' => $this->uuid->getBytes(),
                        'parent_uuid' => $this->parentUuid->getBytes()
                    ],
                    $values
                );
            } else {
                $values = array_merge(
                    ['uuid' => $this->uuid->getBytes()],
                    $values
                );
            }

            $this->db->insert('director_property', $values);

            if (! empty($dynamicArrayItemType)) {
                $this->db->insert('director_property', $dynamicArrayItemType);
            }

            if (! empty($datalist)) {
                $this->db->insert('director_property_datalist', [
                    'property_uuid' => $this->uuid->getBytes(),
                    'list_uuid' => $datalist['uuid'],
                ]);
            }
        } else {
            unset($values['used_count']);
            $used = $this->getValue('used_count') > 0;
            if (! $used) {
                $dbProperty = $this->fetchProperty($this->uuid);
                if (
                    $dbProperty['value_type'] !== $valueType
                    || ($dbProperty['value_type'] === 'dynamic-array' || str_starts_with($dbProperty['value_type'], 'datalist-'))
                ) {
                    $this->db->delete(
                        'director_property',
                        Filter::matchAll(
                            Filter::where('parent_uuid', $this->uuid->getBytes()),
                        )
                    );

                    $this->db->delete(
                        'director_property_datalist',
                        Filter::matchAll(
                            Filter::where(
                                'property_uuid', $this->uuid->getBytes()
                            ),
                        )
                    );
                }

                if ($itemType && ($valueType === 'dynamic-array' || str_starts_with($valueType, 'datalist-'))) {
                    $this->db->insert('director_property', [
                        'uuid' => Uuid::uuid4()->getBytes(),
                        'key_name' => '0',
                        'value_type' => $itemType,
                        'parent_uuid' => $this->uuid->getBytes()
                    ]);

                    if (str_starts_with($valueType, 'datalist-')) {
                        $this->db->insert('director_property_datalist', [
                            'property_uuid' => $this->uuid->getBytes(),
                            'list_uuid' => $datalist['uuid'],
                        ]);
                    }
                }
            } else {
                $this->db->getDbAdapter()->beginTransaction();
                $storedKeyName = $this->db->fetchOne(
                    $this->db->select()
                             ->from('director_property', ['key_name'])
                             ->where('uuid', $this->uuid->getBytes())
                );

                if ($storedKeyName !== $values['key_name']) {
                    $db = $this->db->getDbAdapter();
                    $parent = [];
                    if (! $this->parentUuid) {
                        $rootUuid = $this->uuid;
                    } elseif ($this->isNestedField) {
                        $parent = $this->fetchProperty($this->parentUuid);
                        $rootUuid = Uuid::fromBytes($parent['parent_uuid']);
                    } else {
                        $rootUuid = $this->parentUuid;
                    }

                    $root = $this->fetchProperty($rootUuid);

                    $objectCustomVars = $db->fetchAll(
                        $db->select()
                           ->from(['ihv' => 'icinga_host_var'], [])
                           ->columns([
                               'host_id',
                               'varname',
                               'varvalue',
                               'property_uuid'
                           ])
                           ->where('property_uuid = ?', $rootUuid->getBytes()),
                        [],
                        PDO::FETCH_ASSOC
                    );

                    if (! $this->parentUuid) {
                        foreach ($objectCustomVars as $objectCustomVar) {
                            $this->db->update(
                                'icinga_host_var',
                                ['varname' => $values['key_name']],
                                Filter::matchAll(
                                    Filter::where('property_uuid', $rootUuid->getBytes()),
                                    Filter::where('host_id', $objectCustomVar['host_id'])
                                )
                            );
                        }
                    } else {
                        foreach ($objectCustomVars as $objectCustomVar) {
                            $varValue = json_decode($objectCustomVar['varvalue'], true);
                            if ($root['value_type'] !== 'dynamic-dictionary') {
                                $this->updateObjectCustomVars([$storedKeyName], [$values['key_name']], $varValue);
                            } else {
                                foreach ($varValue as $key => $value) {
                                    if (! $this->isNestedField) {
                                        $this->updateObjectCustomVars([$storedKeyName], [$values['key_name']], $value);
                                    } else {
                                        $parenKey = $parent['key_name'];
                                        $this->updateObjectCustomVars(
                                            [$parenKey, $storedKeyName],
                                            [$parenKey, $values['key_name']],
                                            $value
                                        );
                                    }

                                    $varValue[$key] = $value;
                                }
                            }

                            $this->db->update(
                                'icinga_host_var',
                                ['varvalue' => json_encode($varValue)],
                                Filter::matchAll(
                                    Filter::where('property_uuid', $rootUuid->getBytes()),
                                    Filter::where('host_id', $objectCustomVar['host_id'])
                                )
                            );
                        }
                    }
                }
            }

            $this->db->update(
                'director_property',
                $values,
                Filter::where('uuid', $this->uuid->getBytes())
            );

            $this->db->getDbAdapter()->commit();
        }
    }
}
