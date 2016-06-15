<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaDependencyForm extends DirectorObjectForm
{
    public function setup()
    {
        $this->addObjectTypeElement();
        if (! $this->hasObjectType()) {
            $this->groupMainProperties();
            return;
        }

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Dependency'),
            'required'    => true,
            'description' => $this->translate('Icinga object name for this dependency')
        ));

        $this->addDisabledElement()
             ->addImportsElement()
             ->addObjectsElement()
             ->addBooleanElements()
             ->addPeriodElement()
             ->addAssignmentElements()
             ->addEventFilterElements(array('states'))
             ->groupMainProperties()
             ->setButtons();
    }

    protected function addAssignmentElements()
    {
        if (!$this->object || !$this->object->isApplyRule()) {
            return $this;
        }

        $this->addElement(
            'select',
            'apply_to',
            array(
                'label' => $this->translate('Apply to'),
                'description' => $this->translate(
                    'Whether this dependency should affect hosts or services'
                ),
                'required'    => true,
                'multiOptions' => $this->optionalEnum(
                    array(
                        'host'    => $this->translate('Hosts'),
                        'service' => $this->translate('Services'),
                    )
                )
            )
        );

        $sub = new AssignListSubForm();
        $sub->setObject($this->getObject());
        $sub->setup();
        $sub->setOrder(30);

        $this->addSubForm($sub, 'assignlist');

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
        $hosts = $this->enumAllowedHosts();
	
        if (!empty($hosts)) {
            $this->addElement(
                'select',
                'parent_host_id',
                array(
                    'label' => $this->translate('Parent Host'),
                    'description' => $this->translate(
                        'The parent host.'
                    ),
                    'multiOptions' => $this->optionalEnum($hosts),
                    'sorted'       => true,
                    'class' => "autosubmit",
                    'order' => 10,
                )
            );

        }
	
	$sent_parent=$this->getSentOrObjectValue("parent_host_id");
	$parent_services = $this->enumAllowedServices($sent_parent);

        if (!empty($parent_services)) {
            $this->addElement(
                'select',
                'parent_service_id',
                array(
                    'label' => $this->translate('Parent Service'),
                    'description' => $this->translate(
                        'Optional. The parent service. If omitted this dependency object is treated as host dependency.'
                    ),
                    'multiOptions' => $this->optionalEnum($parent_services),
                    'order'        => 20,
                )
            );

        }

	// If configuring Object, allow selection of child host and/or service, otherwise apply rules will determine child object.
        if ($this->isObject()) {

            if (!empty($hosts) && $this->isObject()) {
                $this->addElement(
                    'select',
                    'child_host_id',
                    array(
                        'label' => $this->translate('Child Host'),
                        'description' => $this->translate(
                            'The child host.'
                        ),
                        'multiOptions' => $this->optionalEnum($hosts),
                        'sorted'       => true,
                        'class' => "autosubmit",
                        'order' => 30,
                    )
                );
            }

            $sent_child=$this->getSentOrObjectValue("child_host_id");
            $child_services = $this->enumAllowedServices($sent_child);
    
            if (!empty($child_services) && ($sent_child != null)) {
    
                $this->addElement(
                    'select',
                    'child_service_id',
                    array(
                        'label' => $this->translate('Child Service'),
                        'description' => $this->translate(
                            'Optional. The child service. If omitted this dependency object is treated as host dependency.'
                        ),
                        'multiOptions' => $this->optionalEnum($child_services),
                        'sorted'       => true,
                        'order'        => 40,
                    )
                );
            }
        }

	$elements=array('parent_host_id','child_host_id','parent_service_id','child_service_id');
        $this->addDisplayGroup($elements, 'related_objects', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => 30,
            'legend' => $this->translate('Related Objects')
        ));


        return $this;
    }

    protected function enumAllowedHosts()
    {
        $obj = $this->db->enumIcingaObjects('host');
        if (empty($obj)) {
            return array();
        }

        return $obj;
    }

    protected function enumAllowedServices($host_id = null)
    {
	/** returns service enumeration.  If host_id is given, services are limited to service on that host, plus all service apply rules
	    (no attempt is made to further limit apply rules)
            If host_id is null, only service apply rules are returned
        **/

	$obj=array();
	if ($host_id != null) { 
        	$obj = $this->db->enumIcingaObjects('service', array('host_id = (?)' => $host_id));
	}

	asort($obj); // do sorting here to keep object services separate from apply rule services
	$obj2 = $this->db->enum('icinga_service', null, array ('object_type IN (?)' => "apply"));
	asort($obj2);

	// indicate filter string in apply rule services
	foreach ($obj2 as $id=>$label) {
		$assigns = $this->db->enum('icinga_service_assignment',array('service_id','filter_string'),array('service_id = (?)' => $id));
		$obj2[$id]=$label.": via assign '".$assigns[$id]."'";
	} 

	$obj += $obj2;
        if (empty($obj)) {
            return array();
        }

        return $obj;
    }

}
