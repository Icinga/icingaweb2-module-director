<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\IcingaTimePeriodRangeForm;
use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Web\Controller\ObjectController;
use ipl\Html\Link;

class TimeperiodController extends ObjectController
{
    public function rangesAction()
    {
        /** @var IcingaTimePeriod $object */
        $object = $this->object;
        $this->tabs()->activate('ranges');
        $this->addTitle($this->translate('Time period ranges'));
        $form = IcingaTimePeriodRangeForm::load()
            ->setTimePeriod($object)
            ->setDb($this->db());

        if ($name = $this->params->get('range')) {
            $this->actions()->add(new Link(
                $this->translate('back'),
                $this->getRequest()->getUrl()->without('range'),
                null,
                ['class' => 'icon-left-big']
            ));
            $form->loadObject([
                'timeperiod_id' => $this->object->id,
                'range_key'     => $name,
                'range_type'    => $this->params->get('range_type')
            ]);
        }
        $form->handleRequest();

        $table = $this->loadTable('icingaTimePeriodRange')
            ->setTimePeriod($this->object);
        $this->content()->add([$form, $table]);
    }
}
