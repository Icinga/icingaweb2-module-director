<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Web\Session;
use ipl\Html\Attributes;
use ipl\Html\Contract\FormSubmitElement;
use ipl\Html\FormElement\FieldsetElement;
use ipl\Html\HtmlElement;
use ipl\I18n\Translation;
use ipl\Validator\InArrayValidator;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class ObjectPropertyForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    public function __construct(
        public readonly DbConnection $db,
        public readonly IcingaObject $object,
        protected ?UuidInterface $propertyUuid = null
    ) {
//        $this->addAttributes(['class' => ['director-form']]);
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));
//        $this->addHtml(HtmlElement::create(
//            'div',
//            Attributes::create(['class' => 'hint']),
//           $this->translate(
//               'Custom properties allow you to easily fill custom variables with'
//               . " meaningful data. It's perfectly legal to override inherited properties."
//               . ' You may for example want to allow "network devices" specifying any'
//               . ' string for vars.snmp_community, but restrict "customer routers" to'
//               . ' a specific set, shown as a dropdown.'
//           )
//        ));

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

        $this->addElement('submit', 'submit', [
            'label' => $this->propertyUuid
                ? $this->translate('Store')
                : $this->translate('Add')
        ]);

        if ($this->propertyUuid) {
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
        $type = $this->object->getShortTableName();
        $query = $this->db->getDbAdapter()
            ->select()
            ->from(['dp' => 'director_property'], ['uuid' => 'dp.uuid', 'key_name' => 'dp.key_name'])
//            ->join(['iop' => 'icinga_' . $type . '_property'], 'dp.uuid = iop.' . $type .  '_uuid')
//            ->where('iop.' . $type . '_uuid = ?', $this->object->uuid)
            ->where('parent_uuid IS NULL');

        $properties = $this->db->getDbAdapter()->fetchAll($query);

        $propUuidKeyPairs = [];
        foreach ($properties as $property) {
            $propUuidKeyPairs[Uuid::fromBytes($property->uuid)->toString()] = $property->key_name;
        }

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
