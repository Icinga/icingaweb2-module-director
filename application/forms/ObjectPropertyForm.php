<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Session;
use ipl\Html\Contract\FormSubmitElement;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ObjectPropertyForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    protected array $properties = [];

    public function __construct(
        public readonly DbConnection $db,
        public readonly IcingaObject $object,
        protected ?UuidInterface $propertyUuid = null
    ) {
    }

    public function getPropertyName(): string
    {
        $propertyUuid = $this->getValue('property');
        if ($propertyUuid) {
            return $this->properties[$propertyUuid] ?? '';
        }

        return '';
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
        $this->addElement(
            'select',
            'property',
            [
                'label'     => $this->translate('Property'),
                'required'  => true,
                'class' => ['autosubmit'],
                'disabledOptions' => [''],
                'value' => '',
                'options'   => array_merge(
                    ['' => $this->translate('Please choose a property')],
                    $this->getProperties()
                )
            ]
        );

        $this->addElement(
            'select',
            'mandatory',
            [
                'label'     => $this->translate('Mandatory'),
                'required'  => true,
                'value'   => 'n',
                'options'   => ['y' => 'Yes', 'n' => 'No']
            ]
        );

        $property = $this->getValue('property');
        $this->addElement('submit', 'submit', [
            'label' => $this->getValue('property')
                ? $this->translate('Store')
                : $this->translate('Add')
        ]);

        if ($property) {
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

    protected function getProperties(): array
    {
        $query = $this->db->getDbAdapter()
            ->select()
            ->from(['dp' => 'director_property'], ['uuid' => 'dp.uuid', 'key_name' => 'dp.key_name'])
            ->where('parent_uuid IS NULL');

        $properties = $this->db->getDbAdapter()->fetchAll($query);

        $propUuidKeyPairs = [];
        foreach ($properties as $property) {
            $propUuidKeyPairs[Uuid::fromBytes($property->uuid)->toString()] = $property->key_name;
        }

        $this->properties = $propUuidKeyPairs;

        return $propUuidKeyPairs;
    }


    public function isValid()
    {
        if ($this->getPressedSubmitElement()->getName() === 'delete') {
            $csrfElement = $this->getElement('CSRFToken');

            return $csrfElement->isValid();
        }

        return parent::isValid();
    }

    public function hasBeenSubmitted()
    {
        if ($this->getPressedSubmitElement() !== null && $this->getPressedSubmitElement()->getName() === 'delete') {
            return true;
        }

        return parent::hasBeenSubmitted();
    }

    protected function onSuccess()
    {
        $formProperty = $this->getValue('property');
        if ($this->getPressedSubmitElement()->getName() === 'delete') {
            $this->db->delete(
                'icinga_host_property',
                Filter::matchAll(
                    Filter::where('host_uuid', $this->object->uuid),
                    Filter::where('property_uuid', Uuid::fromString($this->getValue('property'))->getBytes())
                )
            );

            return;
        }

        if ($this->propertyUuid) {
            if ($this->propertyUuid->toString() !== $formProperty) {
                $this->db->delete(
                    'icinga_host_property',
                    Filter::matchAll(
                        Filter::where('host_uuid', $this->object->uuid),
                        Filter::where('property_uuid', $this->propertyUuid->getBytes())
                    )
                );
            }
        }

        if (! $this->propertyUuid || ($this->propertyUuid->toString() !== $formProperty)) {
            $this->db->insert(
                'icinga_host_property',
                [
                    'host_uuid' => $this->object->uuid,
                    'property_uuid' => Uuid::fromString($this->getValue('property'))->getBytes()
                ]
            );
        }
    }
}
