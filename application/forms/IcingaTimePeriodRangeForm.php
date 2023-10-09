<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Objects\IcingaTimePeriodRange;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaTimePeriodRangeForm extends DirectorObjectForm
{
    /**
     * @var IcingaTimePeriod
     */
    private $period;

    public function setup()
    {
        $this->addHidden('timeperiod_id', $this->period->get('id'));
        $this->addElement('text', 'range_key', array(
            'label'       => $this->translate('Day(s)'),
            'description' => $this->translate(
                'Might be monday, tuesday or 2016-01-28 - have a look at the documentation for more examples'
            ),
        ));

        $this->addElement('text', 'range_value', array(
            'label'       => $this->translate('Timerperiods'),
            'description' => $this->translate(
                'One or more time periods, e.g. 00:00-24:00 or 00:00-09:00,17:00-24:00'
            ),
        ));

        $this->setButtons();
    }

    public function setTimePeriod(IcingaTimePeriod $period)
    {
        $this->period = $period;
        $this->setDb($period->getConnection());
        return $this;
    }

    /**
     * @param IcingaTimePeriodRange $object
     */
    protected function deleteObject($object)
    {
        $key = $object->get('range_key');
        $period = $this->period;
        $period->ranges()->remove($key);
        $period->store();
        $msg = sprintf(
            'Time period range "%s" has been removed from %s',
            $key,
            $period->getObjectName()
        );

        $url = $this->getSuccessUrl()->without(
            ['range', 'range_type']
        );

        $this->setSuccessUrl($url);
        $this->redirectOnSuccess($msg);
    }

    public function onSuccess()
    {
        $object = $this->object();
        if ($object->hasBeenModified()) {
            $this->period->ranges()->setRange(
                $this->getValue('range_key'),
                $this->getValue('range_value')
            );
        }

        if ($this->period->hasBeenModified()) {
            if (! $object->hasBeenLoadedFromDb()) {
                $this->setHttpResponseCode(201);
            }

            $msg = sprintf(
                $object->hasBeenLoadedFromDb()
                ? $this->translate('The %s has successfully been stored')
                : $this->translate('A new %s has successfully been created'),
                $this->translate($this->getObjectShortClassName())
            );

            $this->period->store($this->db);
        } else {
            if ($this->isApiRequest()) {
                $this->setHttpResponseCode(304);
            }
            $msg = $this->translate('No action taken, object has not been modified');
        }
        if ($object instanceof IcingaObject) {
            $this->setSuccessUrl(
                'director/' . strtolower($this->getObjectShortClassName()),
                $object->getUrlParams()
            );
        }

        $this->redirectOnSuccess($msg);
    }
}
