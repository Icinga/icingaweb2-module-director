<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaTemplateChoice;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaTemplateChoiceForm extends DirectorObjectForm
{
    private $choiceType;

    public static function create($type, Db $db)
    {
        return static::load()->setDb($db)->setChoiceType($type);
    }

    public function optionallyLoad($name)
    {
        if ($name !== null) {
            /** @var IcingaTemplateChoice $class - cheating IDE */
            $class = $this->getObjectClassName();
            $this->setObject($class::load($name, $this->getDb()));
        }

        return $this;
    }

    protected function getObjectClassname()
    {
        if ($this->className === null) {
            return 'Icinga\\Module\\Director\\Objects\\IcingaTemplateChoice'
                . ucfirst($this->choiceType);
        }

        return $this->className;
    }

    public function setChoiceType($type)
    {
        $this->choiceType = $type;
        return $this;
    }

    public function setup()
    {
        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Choice name'),
            'required'    => true,
            'description' => $this->translate(
                'This will be shown as a label for the given choice'
            )
        ));

        $this->addElement('textarea', 'description', array(
            'label'       => $this->translate('Description'),
            'rows' => 4,
            'description' => $this->translate(
                'A detailled description explaining what this choice is all about'
            )
        ));

        $this->addElement('extensibleSet', 'members', array(
            'label'       => $this->translate('Available choices'),
            'required'    => true,
            'description' => $this->translate(
                'Your users will be allowed to choose among those templates'
            ),
            'multiOptions' => $this->fetchUnboundTemplates()
        ));

        $this->addElement('text', 'min_required', array(
            'label'       => $this->translate('Minimum required'),
            'description' => $this->translate(
                'Choosing this many options will be mandatory for this Choice.'
                . ' Setting this to zero will leave this Choice optional, setting'
                . ' it to one results in a "required" Choice. You can use higher'
                . ' numbers to enforce multiple options, this Choice will then turn'
                . ' into a multi-selection element.'
            ),
            'value' => 0,
        ));

        $this->addElement('text', 'max_allowed', array(
            'label'       => $this->translate('Allowed maximum'),
            'description' => $this->translate(
                'It will not be allowed to choose more than this many options.'
                . ' Setting it to one (1) will result in a drop-down box, a'
                . ' higher number will turn this into a multi-selection element.'
            ),
            'value' => 1,
        ));

        $this->addElement('select', 'required_template', [
            'label'        => $this->translate('Associated Template'),
            'description'  => $this->translate(
                'Choose Choice Associated Template'
            ),
            'required'     => true,
            'multiOptions' => $this->fetchUnboundTemplates(),
        ]);

        $this->setButtons();
    }

    protected function fetchUnboundTemplates()
    {
        /** @var IcingaTemplateChoice $object */
        $object = $this->object();
        $db = $this->getDb()->getDbAdapter();
        $table = $object->getObjectTableName();
        $query = $db->select()->from(
            ['o' => $table],
            [
                'k' => 'o.object_name',
                'v' => 'o.object_name',
            ]
        )->where("o.object_type = 'template'");
        if ($object->hasBeenLoadedFromDb()) {
            $query->where(
                'o.template_choice_id IS NULL OR o.template_choice_id = ?',
                $object->get('id')
            );
        } else {
            $query->where('o.template_choice_id IS NULL');
        }

        return $db->fetchPairs($query);
    }

    protected function setObjectSuccessUrl()
    {
        /** @var IcingaTemplateChoice $object */
        $object = $this->object();
        $this->setSuccessUrl(
            'director/templatechoice/' . $object->getObjectshortTableName(),
            $object->getUrlParams()
        );
    }
}
