<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaScheduledDowntime;
use Icinga\Module\Director\Objects\IcingaScheduledDowntimeRange;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaScheduledDowntimeRangeForm extends DirectorObjectForm
{
    /** @var IcingaScheduledDowntime */
    private $downtime;

    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $this->addHidden('scheduled_downtime_id', $this->downtime->get('id'));
        $this->addElement('text', 'range_key', [
            'label'       => $this->translate('Day(s)'),
            'description' => $this->translate(
                'Might be monday, tuesday or 2016-01-28 - have a look at the documentation for more examples'
            ),
        ]);

        $this->addElement('text', 'range_value', [
            'label'       => $this->translate('Timeperiods'),
            'description' => $this->translate(
                'One or more time periods, e.g. 00:00-24:00 or 00:00-09:00,17:00-24:00'
            ),
        ]);

        $this->setButtons();
    }

    public function setScheduledDowntime(IcingaScheduledDowntime $downtime)
    {
        $this->downtime = $downtime;
        $this->setDb($downtime->getConnection());
        return $this;
    }

    /**
     * @param IcingaScheduledDowntimeRange $object
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    protected function deleteObject($object)
    {
        $key = $object->get('range_key');
        $downtime = $this->downtime;
        $downtime->ranges()->remove($key);
        $downtime->store();
        $msg = sprintf(
            $this->translate('Time range "%s" has been removed from %s'),
            $key,
            $downtime->getObjectName()
        );

        $url = $this->getSuccessUrl()->without(
            ['range', 'range_type']
        );

        $this->setSuccessUrl($url);
        $this->redirectOnSuccess($msg);
    }

    /**
     * @throws \Icinga\Module\Director\Exception\DuplicateKeyException
     */
    public function onSuccess()
    {
        $object = $this->object();
        if ($object->hasBeenModified()) {
            $this->downtime->ranges()->setRange(
                $this->getValue('range_key'),
                $this->getValue('range_value')
            );
        }

        if ($this->downtime->hasBeenModified()) {
            if (! $object->hasBeenLoadedFromDb()) {
                $this->setHttpResponseCode(201);
            }

            $msg = sprintf(
                $object->hasBeenLoadedFromDb()
                ? $this->translate('The %s has successfully been stored')
                : $this->translate('A new %s has successfully been created'),
                $this->translate($this->getObjectShortClassName())
            );

            $this->downtime->store($this->db);
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
