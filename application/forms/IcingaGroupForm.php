<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaGroupForm extends DirectorObjectForm
{
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
