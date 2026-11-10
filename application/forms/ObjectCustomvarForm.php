<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbConnection;
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

    /** @var array Custom variables linked to the object */
    protected $customVars = [];

    public function __construct(
        public readonly DbConnection $db,
        public readonly IcingaObject $object,
        private readonly array $alreadyAddedUuids = []
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

    /**
     * Whether the "Required" checkbox was ticked
     *
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->getValue('required') === 'y';
    }

    protected function assemble(): void
    {
        $this->addCsrfCounterMeasure(Session::getSession()->getId());
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

        // Nothing enforces it for commands yet, so only templates get the toggle
        if ($this->object->isTemplate()) {
            $this->addElement('checkbox', 'required', [
                'label'       => $this->translate('Required'),
                'description' => $this->translate(
                    'Whether objects using this template must provide a value for this variable'
                )
            ]);
        }

        $this->addElement('submit', 'submit', [
            'label' => $this->translate('Add')
        ]);
    }

    /**
     * Get the custom variables that can be added to the object
     *
     * Lists every top level variable except the ones the object attaches
     * itself. A variable that an imported template or the check command
     * provides can be added, the object then attaches it itself.
     *
     * @return array<string, string> Variable names by uuid string
     */
    protected function getCustomVars(): array
    {
        $type = $this->object->getShortTableName();
        $db = $this->db->getDbAdapter();

        $query = $db
            ->select()
            ->from(['iop' => 'icinga_' . $type . '_property'], ['uuid' => 'iop.property_uuid'])
            ->where('iop.' . $type . '_uuid = ?', DbUtil::quoteBinaryCompat($this->object->get('uuid'), $db));

        $properties = $db->fetchAll(
            $db->select()->from(
                ['odp' => 'director_property'],
                ['uuid' => 'odp.uuid', 'key_name' => 'odp.key_name']
            )->where('parent_uuid IS NULL AND odp.uuid NOT IN (?)', $query)
            ->order('key_name')
        );

        $propUuidKeyPairs = [];
        foreach ($properties as $property) {
            $uuid = DbUtil::binaryResult($property->uuid);
            $uuidStr = Uuid::fromBytes($uuid)->toString();
            if (! in_array($uuidStr, $this->alreadyAddedUuids, true)) {
                $propUuidKeyPairs[$uuidStr] = $property->key_name;
            }
        }

        return $propUuidKeyPairs;
    }
}
