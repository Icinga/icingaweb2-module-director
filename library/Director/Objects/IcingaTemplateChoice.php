<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Web\Form\QuickForm;
use ipl\Translation\TranslationHelper;
use Zend_Form_Element as ZfElement;

class IcingaTemplateChoice extends IcingaObject
{
    private $objectTable;

    protected $defaultProperties = [
        'id'           => null,
        'object_name'  => null,
        'description'  => null,
        'min_required' => 0,
        'max_allowed'  => 1,
    ];

    private $choices;

    private $unstoredChoices;

    public function getObjectTableName()
    {
        return substr($this->table, 0, -16);
    }

    public function createFormElement(QuickForm $form, $imports = [], $namePrefix = 'choice')
    {
        $db = $this->getDb();
        $query = $db->select()->from($this->getObjectTableName(), [
            'value' => 'object_name',
            'label' => 'object_name'
        ])->where('template_choice_id = ?', $this->get('id'));

        $required = $this->isRequired() && !$this->isTemplate();
        $type = $this->allowsMultipleChoices() ? 'multiselect' : 'select';

        $choices = $db->fetchPairs($query);

        $chosen = [];
        foreach ($imports as $import) {
            if (array_key_exists($import, $choices)) {
                $chosen[] = $import;
            }
        }

        $attributes = [
            'label'        => $this->getObjectName(),
            'description'  => $this->get('description'),
            'required'     => $required,
            'ignore'       => true,
            'value'        => $chosen,
            'multiOptions' => $form->optionalEnum($choices),
            'class'        => 'autosubmit'
        ];

        // unused
        if ($type === 'extensibleSet') {
            $attributes['sorted'] = true;
        }

        $key = $namePrefix . $this->get('id');
        return $form->createElement($type, $key, $attributes);
    }

    public function isRequired()
    {
        return (int) $this->min_required > 0;
    }

    public function allowsMultipleChoices()
    {
        return (int) $this->max_allowed > 1;
    }

    public function getChoices()
    {
        if ($this->choices === null) {
            $this->choices = $this->fetchChoices();
        }

        return $this->choices;
    }

    public function fetchChoices()
    {
        if ($this->hasBeenLoadedFromDb()) {
            $db = $this->getDb();
            $query = $db->select()->from(
                ['o' => $this->objectTable],
                ['o.id', 'o.object_name']
            )->where("o.object_type = 'template'")
             ->where('o.template_choice_id = ?', $this->get('id'));

            return $db->fetchPairs($query);
        } else {
            return [];
        }
    }

    public function enumChoices()
    {
        $choices = $this->getChoices();
        return array_combine($choices, $choices);
    }

    /*
     * TODO: mukti?
    protected $relations = [
        'depends_on' => 'IcingaHost',
    ];
    */

    /**
     * @param $type
     * @codingStandardsIgnoreStart
     */
    public function setObject_type($type)
    {
        // @codingStandardsIgnoreEnd
    }
}

/*


Normale Imports -> Windows Basis Checks

Execution speed:

Add field
* Type: host/service template choice
*

Build a host:
* Kinds of templates

*/
