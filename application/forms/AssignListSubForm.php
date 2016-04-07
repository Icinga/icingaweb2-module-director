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

    public function setValue($value)
    {
        var_dump($value);
    }

    public function setup()
    {
        $idx = -1;

        if ($this->object && $this->object->isApplyRule()) {
//            $this->setElementValue('assignlist', $object->assignments()->getFormValues());
            foreach ($this->object->assignments()->getFormValues() as $values) {
                $idx++;
                $sub = new AssignmentSubForm();
                $sub->setObject($this->object);
                $sub->setup();
                $sub->populate($values);
                $this->addSubForm($sub, $idx);
            }
        }

        $idx++;
        $sub = new AssignmentSubForm();
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
                'class' => 'assign-rule'
            )),
            array('Fieldset', array(
                'legend' => 'Assignment rules',
            )),
        ));

        return $this;
    }
}
