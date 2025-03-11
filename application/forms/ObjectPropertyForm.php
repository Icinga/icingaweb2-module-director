<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\Decorator\ExtensibleSetDecorator;
use Icinga\Web\Session;
use ipl\I18n\Translation;
use ipl\Validator\CallbackValidator;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ObjectPropertyForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    protected $properties = [];

    protected $objectProperties = [];

    public function __construct(
        public readonly DbConnection $db,
        public readonly IcingaObject $object,
        protected bool $isRemoval = false,
        protected ?UuidInterface $propertyUuid = null
    ) {
        $this->properties = $this->getProperties();
        $this->objectProperties = $this->getProperties($this->object->uuid);
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
        $propertyElement = $this->createElement(
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
                    $this->isRemoval
                        ? $this->getProperties($this->object->uuid)
                        : $this->getProperties()
                )
            ]
        );

        $this->addElement($propertyElement);

        if (! $this->isRemoval) {
            $propertyElement->addAttributes(
                [
                    'validators' => [new CallbackValidator(function ($value, $validator) {
                        if (array_key_exists($value, $this->objectProperties)) {
                            $validator->addMessage($this->translate('Property already exists'));

                            return false;
                        }

                        return true;
                    })]
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
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->isRemoval
                ? $this->translate('Remove')
                : $this->translate('Add')
        ]);
    }

    protected function getProperties(?string $objectUuid = null): array
    {
        $query = $this->db->getDbAdapter()
            ->select()
            ->from(['dp' => 'director_property'], ['uuid' => 'dp.uuid', 'key_name' => 'dp.key_name'])
            ->where('parent_uuid IS NULL');

        if ($objectUuid) {
            $query->join(['iop' => 'icinga_host_property'], 'iop.property_uuid = dp.uuid')
                ->where('iop.host_uuid = ?', $objectUuid);
        }

        $properties = $this->db->getDbAdapter()->fetchAll($query);

        $propUuidKeyPairs = [];
        foreach ($properties as $property) {
            $propUuidKeyPairs[Uuid::fromBytes($property->uuid)->toString()] = $property->key_name;
        }

        return $propUuidKeyPairs;
    }

    protected function onSuccess()
    {
        $formProperty = $this->getValue('property');
        if ($this->isRemoval) {
            $varName = $this->getPropertyName();
            $propertyAsHostVar = $this->db->getDbAdapter()->fetchAll(
                $this->db
                    ->getDbAdapter()
                    ->select()
                    ->from('icinga_host_var')
                    ->where('varname = ?', $varName)
            );

            $objectId = (int) $this->object->get('id');
            $objectsToCleanUp = [$objectId];
            foreach ($propertyAsHostVar as $propertyAsHostVarRow) {
                $host = IcingaHost::loadWithAutoIncId($propertyAsHostVarRow->host_id, $this->db);

                if (in_array($objectId, $host->listAncestorIds(), true)) {
                    $objectsToCleanUp[] = (int) $host->get('id');
                }
            }

            $where = $this->db->getDbAdapter()->quoteInto('varname = ?', $varName);
            $where .= $this->db->getDbAdapter()->quoteInto(' AND host_id IN (?)', $objectsToCleanUp);
            $this->db->getDbAdapter()
                     ->delete('icinga_host_var', $where);

            $this->db->delete(
                'icinga_host_property',
                Filter::matchAll(
                    Filter::where('host_uuid', $this->object->uuid),
                    Filter::where('property_uuid', Uuid::fromString($formProperty)->getBytes())
                )
            );

            return;
        }

        $this->db->insert(
            'icinga_host_property',
            [
                'host_uuid' => $this->object->uuid,
                'property_uuid' => Uuid::fromString($this->getValue('property'))->getBytes()
            ]
        );
    }
}
