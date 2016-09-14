<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\QuickSubForm;

class AssignmentSubForm extends QuickSubForm
{
    protected $object;

    // @codingStandardsIgnoreStart
    protected $_disableLoadDefaultDecorators = true;
    // @codingStandardsIgnoreEnd

    public function setup()
    {
        $this->addElement('select', 'assign_type', array(
            'multiOptions' => array(
                'assign' => 'assign where',
                'ignore' => 'ignore where',
            ),
            'class' => 'assign-type',
            'value' => 'assign'
        ));
	$varoptions = IcingaHost::enumProperties($this->object->getConnection(), 'host.');
	if($this->object->get('apply_to') === "service") {
	    $varoptions = array_merge($varoptions, IcingaService::enumProperties($this->object->getConnection(), 'service.'));
	}
        $this->addElement('select', 'property', array(
            'label' => $this->translate('Property'),
            'class' => 'assign-property autosubmit',
            'multiOptions' => $this->optionalEnum($varoptions),
        ));
        $this->addElement('select', 'operator', array(
            'label' => $this->translate('Operator'),
            'multiOptions' => array(
                '='  => '=',
                '!=' => '!=',
                '>'  => '>',
                '>=' => '>=',
                '<=' => '<=',
                '<'  => '<',
            ),
            'required' => $this->valueIsEmpty($this->getValue('property')),
            'value' => '=',
            'class' => 'assign-operator',
        ));

        $this->addElement('text', 'expression', array(
            'label'       => $this->translate('Expression'),
            'placeholder' => $this->translate('Expression'),
            'class'       => 'assign-expression',
            'required'    => !$this->valueIsEmpty($this->getValue('property'))
        ));
/*
        $this->addElement('submit', 'remove', array(
            'label'  => '-',
            'ignore' => true
        ));
        $this->addElement('submit', 'add', array(
            'label'  => '+',
            'ignore' => true
        ));
*/
        foreach ($this->getElements() as $el) {
            $el->setDecorators(array('ViewHelper'));
        }
    }

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        return $this;
    }

    public function loadDefaultDecorators()
    {
        $this->setDecorators(array(
            'FormElements',
            array('HtmlTag', array(
                'tag' => 'li',
            )),
                array('FormErrors'),
        ));

        return $this;
    }
}
