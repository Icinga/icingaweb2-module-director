<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Session;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use Ramsey\Uuid\Uuid;

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
        $type = $this->object->getShortTableName();

        $uuids = [];
        $db = $this->db->getDbAdapter();
        $class = DbObjectTypeRegistry::classByType($type);
        foreach ($parents as $parent) {
            $uuids[] = $class::load($parent, $this->object->getConnection())->get('uuid');
        }

        $uuids[] = $this->object->get('uuid');
        $removedProperties = Session::getSession()->getNamespace('director.variables')->get('removed-properties', []);

        $query = $db
            ->select()
            ->from(['dp' => 'director_property'], ['uuid' => 'dp.uuid', 'key_name' => 'dp.key_name'])
            ->joinLeft(['iop' => 'icinga_' . $type . '_property'], 'dp.uuid = iop.property_uuid')
            ->where('dp.parent_uuid IS NULL')
            ->where('iop.' . $type . '_uuid NOT IN (?) OR iop.' . $type . '_uuid IS NULL', $uuids);

        if (! empty($removedProperties)) {
            $query->orWhere('dp.uuid IN (?) AND dp.parent_uuid IS NULL', $removedProperties);
        }

        $properties = $db->fetchAll($query);
        $propUuidKeyPairs = [];
        $alreadyAddedProperties = Session::getSession()->getNamespace('director.variables')->get('added-properties', []);
        foreach ($properties as $property) {
            if (! isset($alreadyAddedProperties[$property->key_name])) {
                $propUuidKeyPairs[Uuid::fromBytes($property->uuid)->toString()] = $property->key_name;
            }
        }

        return $propUuidKeyPairs;
    }
}
