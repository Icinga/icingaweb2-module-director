<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Objects\IcingaDependency;

class IcingaDependencyForm extends DirectorObjectForm
{
    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $this->setupDependencyElements();
    }

    /***
     * @throws \Zend_Form_Exception
     */
    protected function setupDependencyElements()
    {
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
             ->addEventFilterElements(['states'])
             ->groupMainProperties()
             ->addZoneSection()
             ->setButtons();
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
    protected function addZoneSection()
    {
        $this->addZoneElement(true);

        $elements = array(
            'zone_id',
        );
        $this->addDisplayGroup($elements, 'clustering', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => 80,
            'legend' => $this->translate('Zone settings')
        ));

        return $this;
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
    protected function addNameElement()
    {
        $this->addElement('text', 'object_name', [
            'label'       => $this->translate('Name'),
            'required'    => true,
            'description' => $this->translate('Name for the Icinga dependency you are going to create')
        ]);

        return $this;
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
    protected function addAssignmentElements()
    {
        if (!$this->object || !$this->object->isApplyRule()) {
            return $this;
        }

        $this->addElement('select', 'apply_to', [
            'label'        => $this->translate('Apply to'),
            'description'  => $this->translate(
                'Whether this dependency should affect hosts or services'
            ),
            'required'     => true,
            'class'        => 'autosubmit',
            'multiOptions' => $this->optionalEnum([
                'host'    => $this->translate('Hosts'),
                'service' => $this->translate('Services'),
            ])
        ]);

        $applyTo = $this->getSentOrObjectValue('apply_to');

        if (! $applyTo) {
            return $this;
        }

        $suggestionContext = ucfirst($applyTo) . 'FilterColumns';
        $this->addAssignFilter([
            'suggestionContext' => $suggestionContext,
            'required' => true,
            'description' => $this->translate(
                'This allows you to configure an assignment filter. Please feel'
                . ' free to combine as many nested operators as you want'
            )
        ]);

        return $this;
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
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

    /**
     * @return $this
     */
    protected function addBooleanElements()
    {
        $this->addBoolean('disable_checks', [
            'label'       => $this->translate('Disable Checks'),
            'description' => $this->translate(
                'Whether to disable checks when this dependency fails.'
                . ' Defaults to false.'
            )
        ], null);

        $this->addBoolean('disable_notifications', [
            'label'       => $this->translate('Disable Notificiations'),
            'description' => $this->translate(
                'Whether to disable notifications when this dependency fails.'
                . ' Defaults to true.'
            )
        ], null);

        $this->addBoolean('ignore_soft_states', [
            'label'       => $this->translate('Ignore Soft States'),
            'description' => $this->translate(
                'Whether to ignore soft states for the reachability calculation.'
                . ' Defaults to true.'
            )
        ], null);

        return $this;
    }

    /**
     * @return $this
     * @throws \Zend_Form_Exception
     */
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
                'required' => $this->isObject(),
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

        // If configuring Object, allow selection of child host and/or service,
        // otherwise apply rules will determine child object.
        if ($this->isObject()) {
            $this->addElement(
                'text',
                'child_host',
                array(
                    'label' => $this->translate('Child Host'),
                    'description' => $this->translate(
                        'The child host.'
                    ),
                    'value' => $this->getObject()->get('child_host'),
                    'order' => 30,
                    'class'    => "autosubmit director-suggest",
                    'required' => $this->isObject(),
                    'data-suggestion-context' => 'hostnames',
                )
            );

            $sent_child=$this->getSentOrObjectValue("child_host");

            if (!empty($sent_child)) {
                $this->addElement('text', 'child_service', [
                    'label' => $this->translate('Child Service'),
                    'description' => $this->translate(
                        'Optional. The child service. If omitted this dependency'
                        . ' object is treated as host dependency.'
                    ),
                    'class' => "autosubmit director-suggest",
                    'order' => 40,
                    'value' => $this->getObject()->get('child_service'),
                    'data-suggestion-context'  => 'servicenames',
                    'data-suggestion-for-host' => $sent_child,
                ]);
            }
        }

        $elements = ['parent_host', 'child_host', 'parent_service', 'child_service'];
        $this->addDisplayGroup($elements, 'related_objects', [
            'decorators' => [
                'FormElements',
                ['HtmlTag', ['tag' => 'dl']],
                'Fieldset',
            ],
            'order' => 25,
            'legend' => $this->translate('Related Objects')
        ]);

        return $this;
    }

    /**
     * Hint: this is unused. Why?
     *
     * @param IcingaDependency $dependency
     * @return $this
     */
    public function createApplyRuleFor(IcingaDependency $dependency)
    {
        $object = $this->object();
        $object->setImports($dependency->getObjectName());
        $object->set('object_type', 'apply');
        $object->set('object_name', $dependency->getObjectName());

        return $this;
    }
}
