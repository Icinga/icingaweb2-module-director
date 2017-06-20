<?php

namespace Icinga\Module\Director\Objects;

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
