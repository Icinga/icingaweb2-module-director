<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\IcingaScheduledDowntimeRangeForm;
use Icinga\Module\Director\Objects\IcingaScheduledDowntime;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Web\Table\IcingaScheduledDowntimeRangeTable;

class ScheduledDowntimeController extends ObjectController
{
    protected $objectBaseUrl = 'director/scheduled-downtime';

    public function rangesAction()
    {
        /** @var IcingaScheduledDowntime $object */
        $object = $this->object;
        $this->tabs()->activate('ranges');
        $this->addTitle($this->translate('Time period ranges'));
        $form = IcingaScheduledDowntimeRangeForm::load()
            ->setScheduledDowntime($object);

        if (null !== ($name = $this->params->get('range'))) {
            $this->addBackLink($this->url()->without('range'));
            $form->loadObject([
                'scheduled_downtime_id' => $object->get('id'),
                'range_key'  => $name,
                'range_type' => $this->params->get('range_type')
            ]);
        }

        $this->content()->add($form->handleRequest());
        IcingaScheduledDowntimeRangeTable::load($object)->renderTo($this);
    }

    public function getType()
    {
        return 'scheduledDowntime';
    }
}
