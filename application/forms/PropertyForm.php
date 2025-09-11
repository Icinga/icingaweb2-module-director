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

    /** @var bool */
    private $hideKeyNameElement = false;

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

        if ($this->hideKeyNameElement) {
            $db = $this->db->getDbAdapter();

            $query = $db->select()
                ->from('director_property', ['count' => 'COUNT(*)'])
                ->where('parent_uuid = ?', $this->parentUuid->getBytes());
            $this->addElement(
                'hidden',
                'key_name',
                [
                    'label'     => $this->translate('Key'),
                    'required'  => true,
                    'value'     => $db->fetchOne($query)
                ]
            );
        } else {
            $this->addElement(
                'text',
                'key_name',
                [
                    'label'     => $this->translate('Key'),
                    'required'  => true
                ]
            );
        }

        $this->addElement(
            'text',
            'label',
            [
                'label'     => $this->translate('Label'),
                'required'  => true
            ]
        );

        $types = [
            'string' => 'String',
            'number' => 'Number',
            'bool' => 'Boolean',
        ];

        if (! $this->isNestedField) {
            $types += ['array' => 'Array', 'dict' => 'Dictionary'];
        }

        $this->addElement(
            'select',
            'value_type',
            [
                'label'             => $this->translate('Type'),
                'class'             => 'autosubmit',
                'required'          => true,
                'disabledOptions'   => [''],
                'value'             => 'string',
                'options'           => $types
            ]
        );

        $type = $this->getValue('value_type');
        if ($type === 'dict' || $type === 'array') {
            $instantiableElement = $this->createElement(
                'checkbox',
                'instantiable',
                [
                    'label'          => $this->translate('Instantiable by users'),
                    'class'          => 'autosubmit',
                    'checkedValue'   => 'y',
                    'uncheckedValue' => 'n',
                    'value'          => 'n'
                ]
            );

            if ($type === 'dict') {
                $instantiableElement->getAttributes()->add('disabled', $this->parentUuid !== null);
            }

            $this->addElement($instantiableElement);


            if ($type === 'array' && $this->getValue('instantiable') === 'y') {
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
            }
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->uuid ? $this->translate('Save') : $this->translate('Add')
        ]);

        if ($this->uuid) {
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

    public function hasBeenSubmitted()
    {
        if ($this->getPressedSubmitElement() !== null && $this->getPressedSubmitElement()->getName() === 'delete') {
            return true;
        }

        return parent::hasBeenSubmitted();
    }

    public function isValid()
    {
        if ($this->getPressedSubmitElement()->getName() === 'delete') {
            $csrfElement = $this->getElement('CSRFToken');

            return $csrfElement->isValid();
        }

        return parent::isValid();
    }

    protected function onSuccess()
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

            $instantiatedEntry = [];
            if (isset($values['item_type'])) {
                $instantiatedEntry = [
                    'uuid' => Uuid::uuid4()->getBytes(),
                    'key_name' => '0',
                    'value_type' => $values['item_type'],
                    'parent_uuid' => $this->uuid->getBytes(),
                    'instantiable' => 'n',
                ];

                unset($values['item_type']);
            }

            $this->db->insert('director_property', $values);

            if (! empty($instantiatedEntry)) {
                $this->db->insert('director_property', $instantiatedEntry);
            }
        } else {
            $instantiatedEntry = [];
            if (isset($values['item_type']) && $values['instantiable'] === 'y') {
                $instantiatedEntry = [
                    'uuid' => Uuid::uuid4()->getBytes(),
                    'key_name' => '0',
                    'value_type' => $values['item_type'],
                    'parent_uuid' => $this->uuid->getBytes(),
                    'instantiable' => 'n',
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

            if (! empty($instantiatedEntry)) {
                $this->db->insert('director_property', $instantiatedEntry);
            }
        }
    }
}
