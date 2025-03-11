<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Data\Db\DbConnection;
use Icinga\Web\Session;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;
use ipl\Web\Compat\CompatForm;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

class DictionaryForm extends CompatForm
{
    use CsrfCounterMeasure;
    use Translation;

    public function __construct(
        protected DbConnection $db,
        protected ?UuidInterface $uuid = null,
        protected bool $field = false,
        protected ?UuidInterface $parentUuid = null
    ) {
    }

    protected function assemble(): void
    {
        $this->addElement($this->createCsrfCounterMeasure(Session::getSession()->getId()));

        $this->addElement(
            'text',
            'name',
            [
                'label'     => $this->translate('Key'),
                'required'  => true
            ]
        );

        $this->addElement(
            'text',
            'label',
            [
                'label'     => $this->translate('Label'),
                'required'  => true
            ]
        );

        if ($this->field) {
            $types = ['scalar' => 'Scalar'];
        } else {
            $types = ['kv' => 'KV'];
        }

        $this->addElement(
            'select',
            'type',
            [
                'label'             => $this->translate('Type'),
                'class'             => 'autosubmit',
                'required'          => true,
                'disabledOptions'   => [''],
                'options'           => $types
            ]
        );

        $this->addElement('submit', 'btn_submit', [
            'label' => $this->translate('Save')
        ]);
    }

    protected function onSuccess()
    {
        if ($this->uuid === null) {
            if ($this->field) {
                $values = array_merge(
                    [
                        'uuid' => Uuid::uuid4(),
                        'parent_uuid' => $this->parentUuid
                    ],
                    $this->getValues()
                );
            } else {
                $values = array_merge(
                    ['uuid' => Uuid::uuid4()],
                    $this->getValues()
                );
            }

            $this->db->insert('director_dictionary', $values);
        }else {
            $this->db->update(
                'director_dictionary',
                $this->getValues(),
                Filter::where('uuid', $this->uuid)
            );
        }
    }
}