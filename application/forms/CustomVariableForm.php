<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterException;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Db;
use Icinga\Web\Session;
use ipl\Html\Text;
use ipl\I18n\Translation;
use ipl\Web\Common\CalloutType;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use ipl\Web\Url;
use ipl\Web\Widget\ButtonLink;
use ipl\Web\Widget\Callout;
use PDO;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Zend_Db;
use Zend_Db_Select_Exception;

class CustomVariableForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    /** @var bool Whether to hide the key name element or not (checked for the fixed array) */
    private $hideKeyNameElement = false;

    /** @var bool Whether the field is a nested field or not */
    private $isNestedField = false;

    /** @var ?string The key name as stored in the database, used to detect pending renames */
    private ?string $storedKeyName = null;

    public function __construct(
        protected DbConnection $db,
        protected ?UuidInterface $uuid = null,
        protected bool $field = false,
        protected ?UuidInterface $parentUuid = null
    ) {
        $this->getAttributes()->add(['class' => ['custom-variable-form']]);
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

    public function setStoredKeyName(string $keyName): self
    {
        $this->storedKeyName = $keyName;

        return $this;
    }

    public function getStoredKeyName(): string
    {
        return $this->storedKeyName;
    }

    public function isPendingRenameConfirmation(): bool
    {
        return $this->uuid !== null
            && $this->storedKeyName !== null
            && (int) $this->getPopulatedValue('used_count', 0) > 0
            && $this->getPopulatedValue('confirm_rename_change', '') === ''
            && $this->getPopulatedValue('key_name') !== $this->storedKeyName;
    }

    public function hasBeenSubmitted(): bool
    {
        if ($this->isPendingRenameConfirmation()) {
            return false;
        }

        return parent::hasBeenSubmitted();
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $this->addElement('hidden', 'used_count', ['ignore' => true]);
        $used = (int) $this->getValue('used_count') > 0;
        $pendingRename = $this->isPendingRenameConfirmation();

        if ($this->hideKeyNameElement) {
            $db = $this->db->getDbAdapter();
            $query = $db->select()
                ->from('director_property', ['count' => 'COUNT(*)'])
                ->where('parent_uuid = ?', Db\DbUtil::quoteBinaryCompat($this->parentUuid->getBytes(), $db));

            $this->addElement(
                'hidden',
                'key_name',
                ['value' => $db->fetchOne($query)]
            );
        } else {
            $this->addElement(
                'text',
                'key_name',
                [
                    'label'    => $this->translate('Property Key *'),
                    'required' => true
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

        if ($this->parentUuid === null) {
            $this->addElement(
                'select',
                'category_id',
                [
                    'label'             => $this->translate('Category'),
                    'value'             => '',
                    'options'           => ['' => $this->translate('- please choose -')] + $this->fetchCategories()
                ]
            );
        }

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
                'options'           => $types,
                'disabled'          => $used,
                'title'             => $used ? $this->translate(
                    'This property is used in one or more templates and hence the value type'
                    . ' cannot be changed.'
                ) : '',
            ]
        );

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
                    'options'           => array_slice($types, 0, 2),
                    'disabled'          => $used,
                    'title'             => $used ? $this->translate(
                        'This property is used in one or more templates and hence the item type'
                        . ' cannot be changed.'
                    ) : ''
                ]
            );
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
                    'options'           => ['' => $this->translate('- please choose -')] + $this->enumDatalist(),
                    'disabled'          => $used,
                    'title'             => $used ? $this->translate(
                        'This property is used in one or more templates and hence the datalist'
                        . ' cannot be changed.'
                    ) : ''
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
                             'This property is used in one or more templates'
                             . ' and hence the item type cannot be changed.'
                         )
                     )
                     ->setAttribute('disabled', true);
            }
        }

        if ($pendingRename) {
            $this->addElement('checkbox', 'confirm_rename_change', [
                'label'    => $this->translate('Confirm rename')
            ]);

            $this->addHtml(new Callout(
                CalloutType::Warning,
                Text::create($this->translate(
                    'There are objects with this custom variable. Renaming changes the name of the'
                    . ' custom variable in those objects. And this may break the apply rules. Are you'
                    . ' sure you want to rename the custom variable?'
                ))
            ));
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->uuid ? $this->translate('Save') : $this->translate('Add')
        ]);

        if ($this->uuid) {
            $this->getElement('submit')
                ->getWrapper()
                ->prepend(
                    (new ButtonLink(
                        $this->translate('Delete'),
                        Url::fromPath(
                            'director/customvar/delete',
                            ['uuid' => $this->uuid->toString()]
                        ),
                        null,
                        ['class' => ['btn-remove']]
                    ))->openInModal()
                );
        }
    }

    /**
     * Get the datalist options for the field
     *
     * @return array
     */
    private function enumDatalist(): array
    {
        return $this->db->fetchPairs(
            $this->db->select()->from('director_datalist', ['id', 'list_name'])->order('list_name')
        );
    }

    /**
     * Fetch the datalist for the given ID
     *
     * @param int $id
     *
     * @return array
     */
    private function fetchDatalist(int $id): array
    {
        return (array) $this->db->fetchRow(
            $this->db->select()->from('director_datalist', ['*'])
                ->where('id', $id)
        );
    }

    /**
     * Fetch the configured categories for the custom variables
     *
     * @return array
     */
    private function fetchCategories(): array
    {
        return $this->db->fetchPairs(
            $this->db->select()->from('director_datafield_category', ['id', 'category_name'])
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
            ->where('uuid = ?', Db\DbUtil::quoteBinaryCompat($uuid->getBytes(), $db));

        return Db\DbUtil::normalizeRow($db->fetchRow($query, [], Zend_Db::FETCH_ASSOC) ?: []);
    }

    /**
     * Update the custom variable values in the database
     *
     * @param array $path
     * @param array $newPath
     * @param array $item
     *
     * @return void
     */
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
        $datalist = [];
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

        if (
            $this->uuid !== null
            && $this->storedKeyName !== null
            && $this->getPopulatedValue('confirm_rename_change', '') === 'n'
        ) {
            $values['key_name'] = $this->storedKeyName;
        }

        $this->db->getDbAdapter()->beginTransaction();
        if ($this->uuid === null) {
            $this->addNewProperty($values, $datalist, $itemType);
        } else {
            $this->updateExistingProperty($values, $datalist, $itemType);
        }

        $this->db->getDbAdapter()->commit();
    }

    /**
     * Add a new custom variable
     *
     * @param array  $values Form values
     * @param array  $datalist Datalist values if any
     * @param string $itemType Item type if any
     *
     * @return void
     */
    private function addNewProperty(
        array $values,
        array $datalist = [],
        string $itemType = ''
    ): void {
        $this->uuid = Uuid::uuid4();
        $quotedUuid = Db\DbUtil::quoteBinaryCompat($this->uuid->getBytes(), $this->db->getDbAdapter());
        $dynamicArrayItemType = [];
        if ($itemType !== '') {
            $dynamicArrayItemType = [
                'uuid' => Db\DbUtil::quoteBinaryCompat(Uuid::uuid4()->getBytes(), $this->db->getDbAdapter()),
                'key_name' => '0',
                'value_type' => $itemType,
                'parent_uuid' => $quotedUuid
            ];
        }

        if ($this->field) {
            $quotedParentUuid = Db\DbUtil::quoteBinaryCompat($this->parentUuid->getBytes(), $this->db->getDbAdapter());
            $values = array_merge(
                [
                    'uuid' => $quotedUuid,
                    'parent_uuid' => $quotedParentUuid
                ],
                $values
            );
        } else {
            $values = array_merge(
                ['uuid' => $quotedUuid],
                $values
            );
        }

        $this->db->insert('director_property', $values);

        if (! empty($dynamicArrayItemType)) {
            $this->db->insert('director_property', $dynamicArrayItemType);
        }

        if (! empty($datalist)) {
            $this->db->insert('director_property_datalist', [
                'property_uuid' => $quotedUuid,
                'list_uuid' => Db\DbUtil::quoteBinaryCompat(
                    Db\DbUtil::binaryResult($datalist['uuid']),
                    $this->db->getDbAdapter()
                ),
            ]);
        }
    }

    /**
     * Update an existing property
     *
     * @param array  $values Form values
     * @param array  $datalist Datalist values if any
     * @param string $itemType Item type if any
     *
     * @return void
     */
    private function updateExistingProperty(
        array $values,
        array $datalist = [],
        string $itemType = ''
    ): void {
        $used = (int) $this->getValue('used_count') > 0;
        $valueType = $values['value_type'];
        if (isset($values['used_count'])) {
            unset($values['used_count']);
        }

        if (! $used) {
            $dbProperty = $this->fetchProperty($this->uuid);
            if ($dbProperty['value_type'] !== $valueType) {
                $this->db->delete(
                    'director_property',
                    Filter::matchAll(Filter::where(
                        'parent_uuid',
                        Db\DbUtil::quoteBinaryCompat($this->uuid->getBytes(), $this->db->getDbAdapter())
                    ))
                );

                $this->db->delete(
                    'director_property_datalist',
                    Filter::matchAll(Filter::where(
                        'property_uuid',
                        Db\DbUtil::quoteBinaryCompat($this->uuid->getBytes(), $this->db->getDbAdapter())
                    ))
                );

                if ($itemType && ($valueType === 'dynamic-array' || str_starts_with($valueType, 'datalist-'))) {
                    $this->db->insert('director_property', [
                        'uuid' => Db\DbUtil::quoteBinaryCompat(Uuid::uuid4()->getBytes(), $this->db->getDbAdapter()),
                        'key_name' => '0',
                        'value_type' => $itemType,
                        'parent_uuid' => Db\DbUtil::quoteBinaryCompat(
                            $this->uuid->getBytes(),
                            $this->db->getDbAdapter()
                        ),
                    ]);

                    if (str_starts_with($valueType, 'datalist-')) {
                        $this->db->insert('director_property_datalist', [
                            'property_uuid' => Db\DbUtil::quoteBinaryCompat(
                                $this->uuid->getBytes(),
                                $this->db->getDbAdapter()
                            ),
                            'list_uuid' => Db\DbUtil::quoteBinaryCompat(
                                Db\DbUtil::binaryResult($datalist['uuid']),
                                $this->db->getDbAdapter()
                            ),
                        ]);
                    }
                }
            }
        } else {
            $storedKeyName = $this->db->fetchOne(
                $this->db->select()
                    ->from('director_property', ['key_name'])
                    ->where(
                        'uuid = ?',
                        Db\DbUtil::quoteBinaryCompat($this->uuid->getBytes(), $this->db->getDbAdapter())
                    )
            );

            if ($storedKeyName !== $values['key_name']) {
                $this->updateUsedCustomVarNames($storedKeyName, $values['key_name']);
            }
        }

        $this->db->update(
            'director_property',
            $values,
            Filter::where('uuid', Db\DbUtil::quoteBinaryCompat($this->uuid->getBytes(), $this->db->getDbAdapter()))
        );
    }

    /**
     * Update the used custom variable names in the database
     *
     * @param string $storedKeyName
     * @param mixed  $keyName
     *
     * @return void
     */
    private function updateUsedCustomVarNames(string $storedKeyName, mixed $keyName): void
    {
        $db = $this->db->getDbAdapter();
        $parent = [];
        if (! $this->parentUuid) {
            $rootUuid = $this->uuid;
        } elseif ($this->isNestedField) {
            $parent = $this->fetchProperty($this->parentUuid);
            $rootUuid = Uuid::fromBytes(Db\DbUtil::binaryResult($parent['parent_uuid']));
        } else {
            $rootUuid = $this->parentUuid;
        }

        $root = $this->fetchProperty($rootUuid);
        $objectTypes = ['host', 'service', 'notification', 'command', 'user'];

        foreach ($objectTypes as $objectType) {
            $objectCustomVars = $db->fetchAll(
                $db->select()
                   ->from(['ihv' => "icinga_{$objectType}_var"], [])
                   ->columns([
                       "{$objectType}_id",
                       'varname',
                       'varvalue',
                       'property_uuid'
                   ])
                   ->where('property_uuid = ?', Db\DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $db)),
                [],
                PDO::FETCH_ASSOC
            );

            if (! $this->parentUuid) {
                foreach ($objectCustomVars as $objectCustomVar) {
                    $this->db->update(
                        "icinga_{$objectType}_var",
                        ['varname' => $keyName],
                        Filter::matchAll(
                            Filter::where('property_uuid', Db\DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $db)),
                            Filter::where("{$objectType}_id", $objectCustomVar["{$objectType}_id"])
                        )
                    );
                }

                return;
            }

            foreach ($objectCustomVars as $objectCustomVar) {
                $varValue = json_decode($objectCustomVar['varvalue'], true);
                if ($root['value_type'] !== 'dynamic-dictionary') {
                    $this->updateObjectCustomVars([$storedKeyName], [$keyName], $varValue);
                } else {
                    foreach ($varValue as $key => $value) {
                        if (! $this->isNestedField) {
                            $this->updateObjectCustomVars([$storedKeyName], [$keyName], $value);
                        } else {
                            $parenKey = $parent['key_name'];
                            $this->updateObjectCustomVars(
                                [$parenKey, $storedKeyName],
                                [$parenKey, $keyName],
                                $value
                            );
                        }

                        $varValue[$key] = $value;
                    }
                }

                $this->db->update(
                    "icinga_{$objectType}_var",
                    ['varvalue' => json_encode($varValue)],
                    Filter::matchAll(
                        Filter::where('property_uuid', Db\DbUtil::quoteBinaryCompat($rootUuid->getBytes(), $db)),
                        Filter::where("{$objectType}_id", $objectCustomVar["{$objectType}_id"])
                    )
                );
            }
        }
    }
}
