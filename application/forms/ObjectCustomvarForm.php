<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Data\Db\DbObjectTypeRegistry;
use Icinga\Module\Director\Db\DbUtil;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Session;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use Ramsey\Uuid\Uuid;

class ObjectCustomvarForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    protected $customVars = [];

    public function __construct(
        public readonly DbConnection $db,
        public readonly IcingaObject $object
    ) {
        $this->customVars = $this->getCustomVars();
    }

    public function getPropertyName(): string
    {
        $propertyUuid = $this->getValue('property');
        if ($propertyUuid) {
            return $this->customVars[$propertyUuid] ?? '';
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
                'label'     => $this->translate('Variable'),
                'required'  => true,
                'class' => ['autosubmit'],
                'disabledOptions' => [''],
                'value' => '',
                'options'   => array_merge(
                    ['' => $this->translate('Please choose a custom variable to add')],
                    $this->getCustomVars()
                )
            ]
        );

        $this->addElement($propertyElement);

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Add')
        ]);
    }

    protected function getCustomVars(): array
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
            ->from(['dp' => 'director_property'], ['uuid' => 'dp.uuid'])
            ->join(['iop' => 'icinga_' . $type . '_property'], 'dp.uuid = iop.property_uuid', [])
            ->where(
                'dp.parent_uuid IS NULL AND iop.' . $type . '_uuid IN (?)',
                Dbutil::quoteBinaryCompat($uuids, $db)
            );

        if (! empty($removedProperties)) {
            $query->orWhere('dp.uuid IN (?)', $removedProperties);
        }

        $properties = $db->fetchAll(
            $db->select()->from(['odp' => 'director_property'],
                ['uuid' => 'odp.uuid', 'key_name' => 'odp.key_name'])
                ->where('parent_uuid IS NULL AND odp.uuid NOT IN (?)', $query)
        );

        $propUuidKeyPairs = [];
        $alreadyAddedProperties = Session::getSession()
            ->getNamespace('director.variables')->get('added-properties', []);
        foreach ($properties as $property) {
            if (! isset($alreadyAddedProperties[$property->key_name])) {
                $propUuidKeyPairs[Uuid::fromBytes($property->uuid)->toString()] = $property->key_name;
            }
        }

        return $propUuidKeyPairs;
    }
}
