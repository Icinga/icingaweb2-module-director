<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Module\Director\Web\Form\QuickForm;

class IcingaTemplateChoice extends IcingaObject
{
    protected $objectTable;

    protected $defaultProperties = [
        'id'           => null,
        'object_name'  => null,
        'description'  => null,
        'min_required' => 0,
        'max_allowed'  => 1,
    ];

    private $choices;

    private $newChoices;

    public function getObjectShortTableName()
    {
        return substr(substr($this->table, 0, -16), 7);
    }

    public function getObjectTableName()
    {
        return substr($this->table, 0, -16);
    }

    public function createFormElement(QuickForm $form, $imports = [], $namePrefix = 'choice')
    {
        $required = $this->isRequired() && !$this->isTemplate();
        $type = $this->allowsMultipleChoices() ? 'multiselect' : 'select';
        $choices = $this->enumChoices();

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

    public function hasBeenModified()
    {
        if ($this->newChoices !== null && $this->choices !== $this->newChoices) {
            return true;
        }

        return parent::hasBeenModified();
    }

    public function getMembers()
    {
        return $this->enumChoices();
    }

    public function setMembers($members)
    {
        if (empty($members)) {
            $this->newChoices = array();
            return $this;
        }
        $db = $this->getDb();
        $query = $db->select()->from(
            ['o' => $this->getObjectTableName()],
            ['o.id', 'o.object_name']
        )->where("o.object_type = 'template'")
        ->where('o.object_name IN (?)', $members)
        ->order('o.object_name');

        $this->newChoices = $db->fetchPairs($query);
        return $this;
    }

    public function getChoices()
    {
        if ($this->newChoices !== null) {
            return $this->newChoices;
        }

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
                ['o' => $this->getObjectTableName()],
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

    public function onStore()
    {
        parent::onStore();
        if ($this->newChoices !== $this->choices) {
            $this->storeChoices();
        }
    }

    protected function storeChoices()
    {
        $id = $this->getProperty('id');
        $db = $this->getDb();
        $ids = array_keys($this->newChoices);
        $table = $this->getObjectTableName();

        if (empty($ids)) {
            $db->update(
                $table,
                ['template_choice_id' => null],
                $db->quoteInto(
                    sprintf('template_choice_id = %d', $id),
                    $ids
                )
            );
        } else {
            $db->update(
                $table,
                ['template_choice_id' => null],
                $db->quoteInto(
                    sprintf('template_choice_id = %d AND id NOT IN (?)', $id),
                    $ids
                )
            );
            $db->update(
                $table,
                ['template_choice_id' => $id],
                $db->quoteInto('id IN (?)', $ids)
            );
        }
    }

    /**
     * @param $type
     * @codingStandardsIgnoreStart
     */
    public function setObject_type($type)
    {
        // @codingStandardsIgnoreEnd
    }
}
