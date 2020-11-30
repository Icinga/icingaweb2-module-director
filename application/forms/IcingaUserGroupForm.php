<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaUserGroupForm extends DirectorObjectForm
{
    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $this->addHidden('object_type', 'object');

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Usergroup'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this user group')
        ));

        $this->addGroupDisplayNameElement()
             ->addZoneElements()
             ->groupMainProperties()
             ->setButtons();
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
    protected function addZoneElements()
    {
        $this->addZoneElement(true);
        $this->addDisplayGroup(['zone_id'], 'clustering', [
            'decorators' => [
                'FormElements',
                ['HtmlTag', ['tag' => 'dl']],
                'Fieldset',
            ],
            'order'  => self::GROUP_ORDER_CLUSTERING,
            'legend' => $this->translate('Zone settings')
        ]);

        return $this;
    }
}
