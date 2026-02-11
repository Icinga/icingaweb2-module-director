<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbObjectWithSettings;
use Icinga\Module\Director\Hook\JobHook;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Web\Hook;

class DirectorJobForm extends DirectorObjectForm
{
    public function setup()
    {
        $jobTypes = $this->enumJobTypes();

        $this->addElement('select', 'job_class', array(
            'label'        => $this->translate('Job Type'),
            'required'     => true,
            'multiOptions' => $this->optionalEnum($jobTypes),
            'description'  => $this->translate(
                'These are different available job types'
            ),
            'class'        => 'autosubmit'
        ));

        if (! $jobClass = $this->getJobClass()) {
            return;
        }

        if ($desc = $jobClass::getDescription($this)) {
            $this->addHtmlHint($desc);
        }

        $this->addBoolean(
            'disabled',
            array(
                'label'       => $this->translate('Disabled'),
                'description' => $this->translate(
                    'This allows to temporarily disable this job'
                )
            ),
            'n'
        );

        $this->addElement('text', 'run_interval', array(
            'label' => $this->translate('Run interval'),
            'description' => $this->translate(
                'Execution interval for this job, in seconds'
            ),
            'value' => $jobClass::getSuggestedRunInterval($this)
        ));

        $periods = $this->db->enumTimeperiods();

        if (!empty($periods)) {
            $this->addElement(
                'select',
                'timeperiod_id',
                array(
                    'label' => $this->translate('Time period'),
                    'description' => $this->translate(
                        'The name of a time period within this job should be active.'
                        . ' Supports only simple time periods (weekday and multiple'
                        . ' time definitions)'
                    ),
                    'multiOptions' => $this->optionalEnum($periods),
                )
            );
        }

        $this->addElement('text', 'job_name', array(
            'label'       => $this->translate('Job name'),
            'description' => $this->translate(
                'A short name identifying this job. Use something meaningful,'
                . ' like "Import Puppet Hosts"'
            ),
            'required'    => true,
        ));

        $this->addSettings();
        $this->setButtons();
    }

    public function getSentOrObjectSetting($name, $default = null)
    {
        if ($this->hasObject()) {
            $value = $this->getSentValue($name);
            if ($value === null) {
                /** @var DbObjectWithSettings $object */
                $object = $this->getObject();
                return $object->getSetting($name, $default);
            } else {
                return $value;
            }
        } else {
            return $this->getSentValue($name, $default);
        }
    }

    protected function getJobClass($class = null)
    {
        if ($class === null) {
            $class = $this->getSentOrObjectValue('job_class');
        }

        if ($class !== null && array_key_exists($class, $this->enumJobTypes())) {
            return $class;
        }

        return null;
    }

    protected function addSettings($class = null)
    {
        if (! $class = $this->getJobClass($class)) {
            return;
        }

        $class::addSettingsFormFields($this);
        foreach ($this->object()->getSettings() as $key => $val) {
            if ($el = $this->getElement($key)) {
                $el->setValue($val);
            }
        }
    }

    protected function enumJobTypes()
    {
        /** @var JobHook[] $hooks */
        $hooks = Hook::all('Director\\Job');

        $enum = array();

        foreach ($hooks as $hook) {
            $enum[get_class($hook)] = $hook->getName();
        }
        asort($enum);

        return $enum;
    }
}
