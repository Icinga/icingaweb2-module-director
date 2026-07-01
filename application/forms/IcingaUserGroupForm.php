<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use ipl\Web\Url;

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
             ->addAssignmentElements()
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

    protected function addAssignmentElements()
    {
        $this->addAssignFilter([
            'suggestionContext' => 'UserFilterColumns',
            'required' => false,
            'description' => $this->translate(
                'This allows you to configure an assignment filter. Please feel'
                . ' free to combine as many nested operators as you want. The'
                . ' "contains" operator is valid for arrays only. Please use'
                . ' wildcards and the = (equals) operator when searching for'
                . ' partial string matches, like in *.example.com'
            )
        ]);

        return $this;
    }

    protected function deleteObject($object)
    {
        $this->redirectAndExit(
            Url::fromPath('director/usergroup/delete', ['uuid' => $object->getUniqueId()->toString()])
        );
    }
}
