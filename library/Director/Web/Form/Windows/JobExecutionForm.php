<?php

namespace Icinga\Module\Director\Web\Form\Windows;

use gipfl\Translation\TranslationHelper;
use gipfl\Web\Form;

class JobExecutionForm extends Form implements \JsonSerializable
{
    use TranslationHelper;

    const TARGET_TYPE_HOST = 'singleHost';
    const TARGET_TYPE_HOST_LIST = 'hostList';

    protected $useCsrf = false;
    protected $useFormName = false;

    // $this->getAction() -> 'director/windows/job'

    public function getTitle()
    {
        return $this->translate('Run a Job on a Windows System');
    }

    protected function addSingleHostElements()
    {
        $this->addElement('text', 'hostname', [
            'label' => $this->translate('Hostname'),
            'description' => $this->translate('Hilfetext dazu'),
            'required' => true,
        ]);
    }

    protected function addHostListElements()
    {
        $this->addElement('select', 'host_list', [
            'label' => $this->translate('Host List'),
            'description' => $this->translate('Please choose a predefined Host List'),
            'required' => true,
            'options' => [
                null => $this->translate('- please choose -')
            ]
        ]);
    }

    protected function addButtons()
    {
        $this->addElement('submit', 'submitButton', [
            'label' => $this->translate('Run this Job')
        ]);
    }

    protected function assemble()
    {
        $this->addElement('select', 'Job', [
            'label' => $this->translate('Job'),
            'description' => $this->translate('Please choose one of these predefined Job types'),
            'required' => true,
            'options' => [
                null => $this->translate('- please choose -'),
                'installation' => $this->translate('Install Icinga (where not installed)'),
                'upgrade' => $this->translate('Upgrade Icinga, install where missing'),
                'deinstallation' => $this->translate('Remove Icinga and its components'),
            ]
        ]);
        switch ($this->getTargetType()) {
            case self::TARGET_TYPE_HOST:
                $this->addSingleHostElements();
                $this->addButtons();
                break;
            case self::TARGET_TYPE_HOST_LIST:
                $this->addHostListElements();
                $this->addButtons();
                break;
            default:
                // Nothing, no submit button
        }
    }

    protected function getTargetType()
    {
        $this->addElement('select', 'TargetType', [
            'label' => $this->translate('Target Type'),
            'description' => $this->translate('Pick a single host or a bunch of hosts'),
            'required' => true,
            'class'    => 'autosubmit',
            'options' => [
                self::TARGET_TYPE_HOST => $this->translate('Single Host'),
                self::TARGET_TYPE_HOST_LIST => $this->translate('Host List'),
            ],
            'value' => self::TARGET_TYPE_HOST,
        ]);

        return $this->getValue('TargetType');
    }

    public function jsonSerialize(): object
    {
        return FormSerialization::serialize($this);
    }
}
