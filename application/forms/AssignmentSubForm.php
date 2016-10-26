<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaHost;
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

        $this->addElement('dataFilter', 'filter_string', array(
            'columns' => IcingaHost::enumProperties($this->db)
        ));

        foreach ($this->getElements() as $el) {
            $el->setDecorators(array('ViewHelper', 'Errors'));
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
