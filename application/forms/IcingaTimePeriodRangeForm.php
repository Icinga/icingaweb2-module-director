<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaTimePeriod;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaTimePeriodRangeForm extends DirectorObjectForm
{
    /**
     * @var IcingaTimePeriod
     */
    private $period;

    public function setup()
    {
        $this->addHidden('timeperiod_id', $this->period->id);
        $this->addElement('text', 'timeperiod_key', array(
            'label'       => $this->translate('Day(s)'),
            'description' => $this->translate(
                'Might by, monday, tuesday, 2016-01-28 - have a look at the documentation for more examples'
            ),
        ));

        $this->addElement('text', 'timeperiod_value', array(
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
        return $this;
    }
}
