<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\IcingaConfig\IcingaConfigHelper as c;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Form\QuickForm;

class IcingaAssignServiceToHostForm extends QuickForm
{

// NOT TOUCHED

    /**
     *
     * Please note that $object would conflict with logic in parent class
     */
    protected $icingaObject;

    protected $db;

    public function setDb($db)
    {
        $this->db = $db;
        return $this;
    }

    public function setIcingaObject($object)
    {
        $this->icingaObject = $object;
//        $this->className = get_class($object) . 'Field';
        return $this;
    }

    public function setup()
    {
        $this->addHidden('service_id', $this->icingaObject->id);

        if ($this->icingaObject->isTemplate()) {
            $this->addHtmlHint(
                'Assign all services importing this service template to one or'
                . ' more hosts'
            );
        } else {
            $this->addHtmlHint(
                'Assign this service to one or more hosts'
            );
        }

        $this->addElement('select', 'object_type', array(
            'label'        => 'Assign',
            'required'     => true,
            'multiOptions' => $this->optionalEnum(
                array(
                    'host_group'    => $this->translate('to a host group'),
                    'host_property' => $this->translate('by host property'),
                    'host_group_property' => $this->translate('by host group property'),
                )
            ),
            'class' => 'autosubmit'

        ));

        switch ($this->getSentValue('object_type')) {
            case 'host_group':
                $this->addHostGroupElements();
                break;
            case 'host_property':
                $this->addHostPropertyElements();
                break;
            case 'host_property':
                $this->addHostFilterElements();
                break;
        }

        $this->setSubmitLabel(
            $this->translate('Assign')
        );
    }

    protected function addHostGroupElements()
    {
        $this->addElement('select', 'host_id', array(
            'label'        => 'Hostgroup',
            'required'     => true,
            'multiOptions' => $this->optionalEnum($this->db->enumHostgroups())
        ));
    }

    protected function addHostPropertyElements()
    {
        $this->addElement('select', 'host_property', array(
            'label'        => 'Host property',
            'required'     => true,
            'multiOptions' => $this->optionalEnum(IcingaHost::enumProperties($this->db))
        ));
        $this->addElement('text', 'filter_expression', array(
            'label'        => 'Filter expression',
            'required'     => true,
        ));
    }

    protected function addHostFilterElements()
    {
        $this->addElement('text', 'host_filter', array(
            'label'        => 'Host filter string',
            'required'     => true,
        ));
    }

    public function onSuccess()
    {
        switch ($this->getValue('object_type')) {
            case 'host_group':
                $this->db->insert('icinga_service_assignment', array(
                    'service_id'    => $this->getValue('service_id'),
                    // TODO: in?
                    'filter_string' => 'groups=' . $this->getValue('host_group'),
                ));
                break;
            case 'host_property':
                $this->db->insert('icinga_service_assignment', array(
                    'service_id'    => $this->getValue('service_id'),
                    'filter_string' => sprintf(
                        'host.%s=%s',
                        $this->getValue('host_property'),
                        c::renderString($this->getValue('filter_expression'))
                    )
                ));
                break;
            case 'host_filter':
                $this->db->insert('icinga_service_assignment', array(
                    'service_id'    => $this->getValue('service_id'),
                    'filter_string' => $this->getValue('filter_string'),
                ));
                break;
        }
    }
}
