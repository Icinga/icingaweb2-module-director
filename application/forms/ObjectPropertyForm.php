<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Session;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ObjectPropertyForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    protected $properties = [];

    public function __construct(
        public readonly DbConnection $db,
        public readonly IcingaObject $object
    ) {
        $this->properties = $this->getProperties();
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
                    $this->getProperties()
                )
            ]
        );

        $this->addElement($propertyElement);

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Add')
        ]);
    }

    protected function getProperties(): array
    {
        $parents = $this->object->listAncestorIds();

        $uuids = [];
        $db = $this->db->getDbAdapter();
        foreach ($parents as $parent) {
            $uuids[] = IcingaHost::load($parent, $this->object->getConnection())->get('uuid');
        }

        $query = $db
            ->select()
            ->from(['dp' => 'director_property'], ['uuid' => 'dp.uuid', 'key_name' => 'dp.key_name'])
            ->joinLeft(['iop' => 'icinga_host_property'], 'dp.uuid = iop.property_uuid')
            ->where('parent_uuid IS NULL');

        if (! empty($uuids)) {
            $query->where('iop.host_uuid NOT IN (?) OR iop.host_uuid IS NULL', $uuids);
        }

        $properties = $db->fetchAll($query);
        $propUuidKeyPairs = [];
        foreach ($properties as $property) {
            $propUuidKeyPairs[Uuid::fromBytes($property->uuid)->toString()] = $property->key_name;
        }

        return $propUuidKeyPairs;
    }

    protected function onSuccess()
    {
        $this->db->insert(
            'icinga_host_property',
            [
                'host_uuid' => $this->object->uuid,
                'property_uuid' => Uuid::fromString($this->getValue('property'))->getBytes()
            ]
        );
    }
}
