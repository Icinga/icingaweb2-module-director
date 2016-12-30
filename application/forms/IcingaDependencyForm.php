<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Objects\IcingaHost;
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
                'description' => $this->translate(
                    'Whether to disable checks when this dependency fails. Defaults to false.'
                )
            ),
            null
        );

        $this->addBoolean(
            'disable_notifications',
            array(
                'label'       => $this->translate('Disable Notificiations'),
                'description' => $this->translate(
                    'Whether to disable notifications when this dependency fails. Defaults to true.'
                )
            ),
            null
        );

        $this->addBoolean(
            'ignore_soft_states',
            array(
                'label'       => $this->translate('Ignore Soft States'),
                'description' => $this->translate(
                    'Whether to ignore soft states for the reachability calculation. Defaults to true.'
                )
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

        $parentHostId = $this->getSentOrObjectValue('parent_host_id');
        $parentServices = $this->enumAllowedServices($parentHostId);

        if (!empty($parentServices)) {
            $this->addElement(
                'select',
                'parent_service_id',
                array(
                    'label' => $this->translate('Parent Service'),
                    'description' => $this->translate(
                        'Optional. The parent service. If omitted this dependency'
                        . ' object is treated as host dependency.'
                    ),
                    'multiOptions' => $this->optionalEnum($parentServices),
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

            $childHostId = $this->getSentOrObjectValue('child_host_id');
            $childServices = $this->enumAllowedServices($childHostId);

            if (! empty($childServices) && ($childHostId != null)) {

                $this->addElement(
                    'select',
                    'child_service_id',
                    array(
                        'label' => $this->translate('Child Service'),
                        'description' => $this->translate(
                            'Optional. The child service. If omitted this dependency'
                            . ' object is treated as host dependency.'
                        ),
                        'multiOptions' => $this->optionalEnum($childServices),
                        'sorted'       => true,
                        'order'        => 40,
                    )
                );
            }
        }

        $elements = array('parent_host_id','child_host_id','parent_service_id','child_service_id');
        $this->addDisplayGroup($elements, 'related_objects', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order'  => 25,
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

    protected function enumAllowedServices($host_id = null, &$host_templates_done = null)
    {
        // Returns service enumeration. If host_id is given, services are limited
        // to services on that host, or those inherited via host template, plus
        // all service apply rules (no attempt is made to further limit apply rules)
        // If host_id is null, only service apply rules are returned

        $r_services = array();
        $apply_services = array();
        $host_template_services = array();
        $host_services = array();

        if ( $host_templates_done === null) { //don't redo applied service enumeration on recursive calls
            $apply_services = $this->enumApplyServices();
        }

        if ($host_id != null) {
            $host_services = $this->db->enumIcingaObjects('service', array('host_id = ?' => $host_id));
            asort($host_services);

            //services for applicable templates
            $host_templates_done = array();
            $tmp_host = IcingaHost::loadWithAutoIncId($host_id, $this->db);
            $host_templates = $tmp_host->imports()->getObjects();

            foreach ($host_templates as $host_template => $template_obj) {
                if (in_array($template_obj->id, $host_templates_done)) continue;

                $host_templates_done[] = $template_obj->id;
                $get_template_services = $this->enumAllowedServices($template_obj->id, $host_templates_done); //recursively get services for this host's template tree
                // indicate host template name in 'inherited' services
                foreach ($get_template_services as $id => &$label) {
                    if (!preg_match("/via host template/", $label)) $get_template_services[$id]= $label.': via host template '.$host_template;
                }
                $host_template_services+=$get_template_services;
            }
            asort($host_template_services);
        }

        $r_services += $host_services += $host_template_services += $apply_services;

        return $r_services;
    }

    protected function enumApplyServices()
    {
        $db = $this->db->getDbAdapter();
        $query = $db->select()->from(
            array('s' => 'icinga_service'),
            array(
                's.id',
                's.object_name',
                's.assign_filter'
            )
        )->where('s.object_type = ?', 'apply');
        $list = $db->fetchAll($query);
        $services = array();
        foreach ($list as $row) {
            $services[$row->id] = sprintf(
                '%s where %s',
                $row->object_name,
                Filter::fromQueryString($row->assign_filter)
            );
        }

        asort($services);
        return $services;
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
