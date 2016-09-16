<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaDependency;

class IcingaDependencyForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->setupDependencyElements();
    }

    protected function setupDependencyElements() {

        $this->addObjectTypeElement();
        if (! $this->hasObjectType()) {
            $this->groupMainProperties();
            return;
        }

        $this->addNameElement()
             ->addDisabledElement()
             ->addImportsElement()
             ->addObjectsElement()
             ->addBooleanElements()
             ->addPeriodElement()
             ->addAssignmentElements()
             ->addEventFilterElements(array('states'))
             ->groupMainProperties()
             ->setButtons();
    }

    protected function addNameElement()
    {
        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Name'),
            'required'    => true,
            'description' => $this->translate('Name for the Icinga dependency you are going to create')
        ));

        return $this;
    }


    protected function addAssignmentElements()
    {
        if (!$this->object || !$this->object->isApplyRule()) {
            return $this;
        }

        $this->addElement('select', 'apply_to', array(
            'label'        => $this->translate('Apply to'),
            'description'  => $this->translate(
                'Whether this dependency should affect hosts or services'
            ),
            'required'     => true,
            'class'        => 'autosubmit',
            'multiOptions' => $this->optionalEnum(
                array(
                    'host'    => $this->translate('Hosts'),
                    'service' => $this->translate('Services'),
                )
            )
        ));

        $applyTo = $this->getSentOrObjectValue('apply_to');

        if ($applyTo === 'host') {
            $columns = IcingaHost::enumProperties($this->db, 'host.');
        } elseif ($applyTo === 'service') {
            // TODO: Also add host properties
            $columns = IcingaService::enumProperties($this->db, 'service.');
        } else {
            return $this;
        }

        $this->addAssignFilter(array(
            'columns' => $columns,
            'required' => true,
            'description' => $this->translate(
                'This allows you to configure an assignment filter. Please feel'
                . ' free to combine as many nested operators as you want'
            )
        ));
        return $this;
    }

    protected function addPeriodElement()
    {
        $periods = $this->db->enumTimeperiods();
        if (empty($periods)) {
            return $this;
        }

        $this->addElement(
            'select',
            'period_id',
            array(
                'label' => $this->translate('Time period'),
                'description' => $this->translate(
                    'The name of a time period which determines when this'
                    . ' notification should be triggered. Not set by default.'
                ),
                'multiOptions' => $this->optionalEnum($periods),
            )
        );

        return $this;
    }

    protected function addBooleanElements() {

        $this->addBoolean(
            'disable_checks',
            array(
                'label'       => $this->translate('Disable Checks'),
                'description' => $this->translate('Whether to disable checks when this dependency fails. Defaults to false.')
            ),
            null
        );

        $this->addBoolean(
            'disable_notifications',
            array(
                'label'       => $this->translate('Disable Notificiations'),
                'description' => $this->translate('Whether to disable notifications when this dependency fails. Defaults to true.')
            ),
            null
        );

        $this->addBoolean(
            'ignore_soft_states',
            array(
                'label'       => $this->translate('Ignore Soft States'),
                'description' => $this->translate('Whether to ignore soft states for the reachability calculation. Defaults to true.')
            ),
            null
        );

        return $this;
    }

    protected function addObjectsElement()
    {
        $this->addElement(
            'text',
            'parent_host',
            array(
                'label' => $this->translate('Parent Host'),
                'description' => $this->translate(
                    'The parent host.'
                ),
                'class' => "autosubmit director-suggest",
                'data-suggestion-context' => 'hostnames',
                'order' => 10,
                'value' => $this->getObject()->get('parent_host')

            )
        );
        $sent_parent=$this->getSentOrObjectValue("parent_host");

        if (!empty($sent_parent) || $this->object->isApplyRule()) {
            $this->addElement(
                'text',
                'parent_service',
                array(
                    'label' => $this->translate('Parent Service'),
                    'description' => $this->translate(
                        'Optional. The parent service. If omitted this dependency object is treated as host dependency.'
                    ),
                    'class' => "autosubmit director-suggest",
                    'data-suggestion-context' => 'servicenames',
                    'data-suggestion-for-host' => $sent_parent,
                    'order' => 20,
                    'value' => $this->getObject()->get('parent_service')

                )
            );

        }

        // If configuring Object, allow selection of child host and/or service, otherwise apply rules will determine child object.
        if ($this->isObject()) {
            $this->addElement(
                'text',
                'child_host',
                array(
                    'label' => $this->translate('Child Host'),
                    'description' => $this->translate(
                        'The child host.'
                    ),
                    'class' => "autosubmit director-suggest",
                    'data-suggestion-context' => 'hostnames',
                    'order' => 30,
                    'value' => $this->getObject()->get('child_host')
                )
            );
	
            $sent_child=$this->getSentOrObjectValue("child_host");
    
            if (!empty($sent_child)) {
                $this->addElement(
                    'text',
                    'child_service',
                    array(
                        'label' => $this->translate('Child Service'),
                        'description' => $this->translate(
                            'Optional. The child service. If omitted this dependency object is treated as host dependency.'
                        ),
                        'class' => "autosubmit director-suggest",
                        'data-suggestion-context' => 'servicenames',
                        'data-suggestion-for-host' => $sent_child,
                        'order' => 40,
                        'value' => $this->getObject()->get('child_service')
    
                    )
                );


            }
        }

        $elements=array('parent_host','child_host','parent_service','child_service');
        $this->addDisplayGroup($elements, 'related_objects', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => 25,
            'legend' => $this->translate('Related Objects')
        ));


        return $this;
    }

    public function createApplyRuleFor(IcingaDependency $dependency)
    {
        $object = $this->object();
        $object->imports = $dependency->object_name;
        $object->object_type = 'apply';
        $object->object_name = $dependency->object_name;
        return $this;
    }


}
