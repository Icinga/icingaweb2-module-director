<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\QuickSubForm;

class AssignListSubForm extends QuickSubForm
{
    protected $object;

    public function setObject($object)
    {
        $this->object = $object;
        return $this;
    }

    public function setup()
    {
        $idx = -1;

        if ($this->object && $this->object->supportsAssignments()) {

            foreach ($this->object->assignments()->getValues() as $values) {
                $idx++;
                $sub = $this->loadForm('assignmentSub');
                $sub->setObject($this->object);
                $sub->setup();
                $sub->populate($values);
                $this->addSubForm($sub, $idx);
            }
        }

        $idx++;
        $sub = $this->loadForm('assignmentSub');
        $sub->setObject($this->object);
        $sub->setup();
        $this->addSubForm($sub, $idx);
        $this->addElement('submit', 'addmore', array(
            'label'  => $this->translate('Add more'),
            'class'  => 'link-button icon-plus',
            'ignore' => true,
        ));
        $this->getElement('addmore')->setDecorators(array('ViewHelper'));

    }

    public function loadDefaultDecorators()
    {
        $this->setDecorators(array(
            'FormElements',
            array('HtmlTag', array(
                'tag' => 'ul',
                'class' => 'assign-rule required'
            )),
            array('Fieldset', array(
                'legend' => 'Assignment rules',
            )),
        ));

        return $this;
    }
}
