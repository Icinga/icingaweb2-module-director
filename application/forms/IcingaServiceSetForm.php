<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaServiceSetForm extends DirectorObjectForm
{
    protected $host;

    public function setup()
    {
        if ($this->host === null) {
            $this->setupTemplate();
        } else {
            $this->setupHost();
        }

        $this->setupFields()
             ->setButtons();
    }

    protected function setupFields()
    {
        $object = $this->object();

        $this->assertResolvedImports();

        if ($this->hasBeenSent() && $services = $this->getSentValue('service')) {
            $object->service = $services;
        }

        if ($this->assertResolvedImports()) {
            $this->fieldLoader($object)
                ->loadFieldsForMultipleObjects($object->getServiceObjects());
        }

        return $this;
    }

    protected function setupTemplate()
    {
        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Service set name'),
            'description' => $this->translate(
                'A short name identifying this set of services'
            ),
            'required'    => true,
        ));

        $this->addHidden('object_type', 'template');
        $this->addDescriptionElement()
            ->addAssignmentElements();
    }

    protected function setupHost()
    {
       $object = $this->object();
        if ($this->hasBeenSent()) {
            $object->object_name = $object->imports = $this->getSentValue('imports');
        }

        if (! $object->hasBeenLoadedFromDb()) {
            $this->addSingleImportsElement();
        }

        if (count($object->imports)) {
            $this->addHtmlHint(
                $this->getView()->escape(
                    $object->getResolvedProperty('description')
                )
            );
        }

        $this->addHidden('object_type', 'object');
        $this->addHidden('host_id', $this->host->id);
    }

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
    }
    protected function addSingleImportsElement()
    {
        $enum = $this->enumAllowedTemplates();

        $this->addElement('select', 'imports', array(
            'label'        => $this->translate('Service set'),
            'description'  => $this->translate(
                'The service set that should be assigned to this host'
            ),
            'required'     => true,
            'multiOptions' => $this->optionallyAddFromEnum($enum),
            'class'        => 'autosubmit'
        ));

        return $this;
    }

    protected function addDescriptionElement()
    {
        $this->addElement('textarea', 'description', array(
            'label'       => $this->translate('Description'),
            'description' => $this->translate(
                'A meaningful description explaining your users what to expect'
                . ' when assigning this set of services'
            ),
            'rows'        => '3',
            'required'    => ! $this->isTemplate(),
        ));

        return $this;
    }

    protected function addAssignmentElements()
    {
        $this->addAssignFilter(array(
            'columns' => IcingaHost::enumProperties($this->db, 'host.'),
            'description' => $this->translate(
                'This allows you to configure an assignment filter. Please feel'
                . ' free to combine as many nested operators as you want. You'
                . ' might also want to skip this, define it later and/or just'
                . ' add this set of services to single hosts'
            )
        ));

        return $this;
    }
}
