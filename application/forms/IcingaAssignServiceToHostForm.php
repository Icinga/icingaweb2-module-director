<?php

namespace Icinga\Module\Director\Forms;

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
                    'host'          => $this->translate('to a single host'),
                    'host_template' => $this->translate('to a host template'),
                    'host_group'    => $this->translate('to a host group'),
                    'host_property' => $this->translate('by host property'),
                    'host_group_property' => $this->translate('by host group property'),
                )
            ),
            'class' => 'autosubmit'

        ));

        switch ($this->getSentValue('object_type')) {
            case 'host':
                $this->addHostElements();
                break;
            case 'host_template':
                $this->addHostTemplateElements();
                break;
            case 'host_group':
                $this->addHostGroupElements();
                break;
            case 'host_property':
                $this->addHostPropertyElements();
                break;
        }

        $fields = $this->icingaObject->getResolvedFields();
print_r(array_keys((array) $fields));

        $this->setSubmitLabel(
            $this->translate('Assign')
        );
    }

    protected function addHostElements()
    {
        $this->addElement('select', 'host_id', array(
            'label'        => 'Host',
            'required'     => true,
            'multiOptions' => $this->optionalEnum($this->db->enumHosts())
        ));
    }

    protected function addHostTemplateElements()
    {
        $this->addElement('select', 'host_id', array(
            'label'        => 'Host template',
            'required'     => true,
            'multiOptions' => $this->optionalEnum($this->db->enumHostTemplates())
        ));
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
    }

    public function onSuccess()
    {
        switch ($this->getValue('object_type')) {
            case 'host':
            case 'host_template':
                $this->db->insert('icinga_host_service', array(
                    'host_id'    => $this->getValue('host_id'),
                    'service_id' => $this->getValue('service_id'),
                ));
                break;
            case 'host_group':
                /*
                $this->db->insert('icinga_host_group_service', array(
                    'host_id'    => $this->getValue('hostgroup_id'),
                    'service_id' => $this->getValue('service_id'),
                ));
                */
                break;
        }
    }
}
