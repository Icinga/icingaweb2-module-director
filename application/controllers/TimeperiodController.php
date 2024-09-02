<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\IcingaTimePeriodRangeForm;
use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Web\Table\IcingaTimePeriodRangeTable;

class TimeperiodController extends ObjectController
{
    public function rangesAction()
    {
        /** @var IcingaTimePeriod $object */
        $object = $this->object;
        $this->tabs()->activate('ranges');
        $this->addTitle($this->translate('Time period ranges'));
        $form = IcingaTimePeriodRangeForm::load()
            ->setTimePeriod($object);

        if (null !== ($name = $this->params->get('range'))) {
            $this->addBackLink($this->url()->without('range'));
            $form->loadObject([
                'timeperiod_id' => $object->get('id'),
                'range_key'     => $name,
                'range_type'    => $this->params->get('range_type')
            ]);
        }

        $this->content()->add($form->handleRequest());
        IcingaTimePeriodRangeTable::load($object)->renderTo($this);
    }

    protected function hasBasketSupport()
    {
        return true;
    }
}
