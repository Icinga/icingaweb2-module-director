<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Data\Db\DbObject;
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
            'order' => self::GROUP_ORDER_CLUSTERING,
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
            'description' => $this->translate(
                'This allows you to configure an assignment filter. Please feel'
                . ' free to combine as many nested operators as you want. The'
                . ' "contains" operator is valid for arrays only. Please use'
                . ' wildcards and the = (equals) operator when searching for'
                . ' partial string matches, like in *.example.com'
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
        $dependency = $this->getObject();
        $parentHost = $dependency->get('parent_host');
        if ($parentHost === null) {
            $parentHostVar = $dependency->get('parent_host_var');
            if (\strlen($parentHostVar) > 0) {
                $parentHost = '$' . $dependency->get('parent_host_var') . '$';
            }
        }
        $this->addElement('text', 'parent_host', [
            'label' => $this->translate('Parent Host'),
            'description' => $this->translate(
                'The parent host. You might want to refer Host Custom Variables'
                . ' via $host.vars.varname$'
            ),
            'class' => "autosubmit director-suggest",
            'data-suggestion-context' => 'hostnames',
            'order' => 10,
            'required' => $this->isObject(),
            'value' => $parentHost
        ]);
        $sentParent = $this->getSentOrObjectValue('parent_host');

        if (!empty($sentParent) || $dependency->isApplyRule()) {
            $parentService = $dependency->get('parent_service');
            if ($parentService === null) {
                $parentServiceVar = $dependency->get('parent_service_by_name');
                if (\strlen($parentServiceVar) > 0) {
                    $parentService = '$' . $dependency->get('parent_service_by_name') . '$';
                }
            }
            $this->addElement('text', 'parent_service', [
                    'label' => $this->translate('Parent Service'),
                    'description' => $this->translate(
                        'Optional. The parent service. If omitted this dependency'
                        . ' object is treated as host dependency. You might want to refer'
                        . ' Service Custom Variables via $service.vars.varname$'
                    ),
                    'class' => "autosubmit director-suggest",
                    'data-suggestion-context' => 'servicenames',
                    'data-suggestion-for-host' => $sentParent,
                    'order' => 20,
                    'value' => $parentService
                ]);
        }

        // If configuring Object, allow selection of child host and/or service,
        // otherwise apply rules will determine child object.
        if ($dependency->isObject()) {
            $this->addElement('text', 'child_host', [
                'label'       => $this->translate('Child Host'),
                'description' => $this->translate('The child host.'),
                'value'       => $dependency->get('child_host'),
                'order'       => 30,
                'class'       => 'autosubmit director-suggest',
                'required'    => $this->isObject(),
                'data-suggestion-context' => 'hostnames',
            ]);

            $sentChild = $this->getSentOrObjectValue('child_host');

            if (!empty($sentChild)) {
                $this->addElement('text', 'child_service', [
                    'label' => $this->translate('Child Service'),
                    'description' => $this->translate(
                        'Optional. The child service. If omitted this dependency'
                        . ' object is treated as host dependency.'
                    ),
                    'class' => 'autosubmit director-suggest',
                    'order' => 40,
                    'value' => $this->getObject()->get('child_service'),
                    'data-suggestion-context'  => 'servicenames',
                    'data-suggestion-for-host' => $sentChild,
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
            'order' => self::GROUP_ORDER_RELATED_OBJECTS,
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

    protected function handleProperties(DbObject $object, &$values)
    {
        if ($this->hasBeenSent()) {
            if (isset($values['parent_host'])
                && $this->isCustomVar($values['parent_host'])
            ) {
                $values['parent_host_var'] = \trim($values['parent_host'], '$');
                $values['parent_host'] = '';
            }
            if (isset($values['parent_service'])
                && $this->isCustomVar($values['parent_service'])
            ) {
                $values['parent_service_by_name'] = \trim($values['parent_service'], '$');
                $values['parent_service'] = '';
            }
        }

        parent::handleProperties($object, $values);
    }

    protected function isCustomVar($string)
    {
        return \preg_match('/^\$(?:host|service)\.vars\..+\$$/', $string);
        // Eventually: return \preg_match('/^\$(?:host|service)\.vars\..+\$$/', $string);
    }
}
