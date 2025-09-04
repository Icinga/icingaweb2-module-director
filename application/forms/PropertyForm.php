<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Web\Session;
use ipl\Html\Contract\FormSubmitElement;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

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
    }

    public function getUUid(): ?UuidInterface
    {
        return $this->uuid;
    }

    public function getParentUUid(): ?UuidInterface
    {
        return $this->parentUuid;
    }

    public function setHideKeyNameElement(bool $hideKeyNameElement): self
    {
        $this->hideKeyNameElement = $hideKeyNameElement;

        return $this;
    }

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
            ['label'     => $this->translate('Property Label')]
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
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->uuid ? $this->translate('Save') : $this->translate('Add')
        ]);

        if ($this->uuid) {
            // TODO: Ask for confirmation before deleting
            /** @var FormSubmitElement $deleteButton */
            $deleteButton = $this->createElement(
                'submit',
                'delete',
                [
                    'label'          => $this->translate('Delete'),
                    'class'          => 'btn-remove',
                    'formnovalidate' => true
                ]
            );

            $this->registerElement($deleteButton);
            $this->getElement('submit')
                ->getWrapper()
                ->prepend($deleteButton);
        }
    }

    public function hasBeenSubmitted(): bool
    {
        if ($this->getPressedSubmitElement() !== null && $this->getPressedSubmitElement()->getName() === 'delete') {
            return true;
        }

        return parent::hasBeenSubmitted();
    }

    public function isValid(): bool
    {
        if ($this->getPressedSubmitElement()->getName() === 'delete') {
            $csrfElement = $this->getElement('CSRFToken');

            return $csrfElement->isValid();
        }

        return parent::isValid();
    }

    protected function onSuccess(): void
    {
        if ($this->getPressedSubmitElement()->getName() === 'delete') {
            $this->db->delete('director_property', Filter::where('parent_uuid', $this->uuid->getBytes()));
            $this->db->delete('director_property', Filter::where('uuid', $this->uuid->getBytes()));

            return;
        }

        $values = $this->getValues();
        if ($this->uuid === null) {
            $this->uuid = Uuid::uuid4();
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

            $dynamicArrayItemType = [];
            if (isset($values['item_type'])) {
                $dynamicArrayItemType = [
                    'uuid' => Uuid::uuid4()->getBytes(),
                    'key_name' => '0',
                    'value_type' => $values['item_type'],
                    'parent_uuid' => $this->uuid->getBytes()
                ];

                unset($values['item_type']);
            }

            $this->db->insert('director_property', $values);

            if (! empty($dynamicArrayItemType)) {
                $this->db->insert('director_property', $dynamicArrayItemType);
            }
        } else {
            $dynamicArrayItemType = [];
            if (isset($values['item_type']) && $values['value_type'] === 'dynamic-array') {
                $dynamicArrayItemType = [
                    'uuid' => Uuid::uuid4()->getBytes(),
                    'key_name' => '0',
                    'value_type' => $values['item_type'],
                    'parent_uuid' => $this->uuid->getBytes()
                ];

                unset($values['item_type']);
            }

            $this->db->update(
                'director_property',
                $values,
                Filter::where('uuid', $this->uuid->getBytes())
            );

            $this->db->delete(
                'director_property',
                Filter::matchAll(
                    Filter::where('parent_uuid', $this->uuid->getBytes()),
                    Filter::where('key_name', '0')
                )
            );

            if (! empty($dynamicArrayItemType)) {
                $this->db->insert('director_property', $dynamicArrayItemType);
            }
        }
    }
}
