<?php

namespace Icinga\Module\Director\Objects;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Director\DirectorObject\Automation\ExportInterface;
use Icinga\Module\Director\Web\Form\QuickForm;

class IcingaTemplateChoice extends IcingaObject implements ExportInterface
{
    protected $objectTable;

    protected $defaultProperties = [
        'id'           => null,
        'object_name'  => null,
        'description'  => null,
        'min_required' => 0,
        'max_allowed'  => 1,
        'required_template_id' => null,
        'allowed_roles'        => null,
    ];

    private $choices;

    private $newChoices;

    public function getObjectShortTableName()
    {
        return substr(substr($this->table, 0, -16), 7);
    }

    public function getUniqueIdentifier()
    {
        return $this->getObjectName();
    }

    public function isMainChoice()
    {
        return $this->hasBeenLoadedFromDb()
            && $this->connection->settings()->get('main_host_choice');
    }

    public function getObjectTableName()
    {
        return substr($this->table, 0, -16);
    }

    /**
     * @param QuickForm $form
     * @param array $imports
     * @param string $namePrefix
     * @return \Zend_Form_Element
     * @throws \Zend_Form_Exception
     */
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
        return (int) $this->get('min_required') > 0;
    }

    public function allowsMultipleChoices()
    {
        return (int) $this->get('max_allowed') > 1;
    }

    public function hasBeenModified()
    {
        if ($this->newChoices !== null && ($this->choices ?? $this->fetchChoices()) !== $this->newChoices) {
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
             ->where('o.template_choice_id = ?', $this->get('id'))
             ->order('o.object_name');

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

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
    public function onStore()
    {
        parent::onStore();
        if ($this->newChoices !== $this->choices) {
            $this->storeChoices();
        }
    }

    /**
     * @throws \Zend_Db_Adapter_Exception
     */
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
     * @param $roles
     * @throws ProgrammingError
     * @codingStandardsIgnoreStart
     */
    public function setAllowed_roles($roles)
    {
        // @codingStandardsIgnoreEnd
        $key = 'allowed_roles';
        if (is_array($roles)) {
            $this->reallySet($key, json_encode($roles));
        } elseif (null === $roles) {
            $this->reallySet($key, null);
        } else {
            throw new ProgrammingError(
                'Expected array or null for allowed_roles, got %s',
                var_export($roles, true)
            );
        }
    }

    /**
     * @return array|null
     * @codingStandardsIgnoreStart
     */
    public function getAllowed_roles()
    {
        // @codingStandardsIgnoreEnd

        // Might be removed once all choice types have allowed_roles
        if (! array_key_exists('allowed_roles', $this->properties)) {
            return null;
        }

        $roles = $this->getProperty('allowed_roles');
        if (is_string($roles)) {
            return json_decode($roles);
        } else {
            return $roles;
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
