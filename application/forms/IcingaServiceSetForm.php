<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Web\Form\Validate\NamePattern;

class IcingaServiceSetForm extends DirectorObjectForm
{
    protected $host;

    protected $listUrl = 'director/services/sets';

    public function setup()
    {
        if ($this->host === null) {
            $this->setupTemplate();
        } else {
            $this->setupHost();
        }

        $this->setButtons();
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

        $rName = 'director/service_set/filter-by-name';
        foreach ($this->getAuth()->getRestrictions($rName) as $restriction) {
            $this->getElement('object_name')->addValidator(
                new NamePattern($restriction)
            );
        }

        $this->addHidden('object_type', 'template');
        $this->addDescriptionElement()
            ->addAssignmentElements();
    }

    protected function setObjectSuccessUrl()
    {
        if ($this->host) {
            $this->setSuccessUrl(
                'director/host/services',
                array('name' => $this->host->getObjectName())
            );
        } else {
            parent::setObjectSuccessUrl();
        }
    }

    protected function setupHost()
    {
        $object = $this->object();
        if ($this->hasBeenSent()) {
            $object->set('object_name', $this->getSentValue('imports'));
            $object->set('imports', $object->object_name);
        }

        if (! $object->hasBeenLoadedFromDb()) {
            $this->addSingleImportsElement();
        }

        if (count($object->get('imports'))) {
            $this->addHtmlHint(
                $object->getResolvedProperty('description')
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
        if (! $this->hasPermission('director/service_set/apply')) {
            return $this;
        }

        $this->addAssignFilter([
            'suggestionContext' => 'HostFilterColumns',
            'description' => $this->translate(
                'This allows you to configure an assignment filter. Please feel'
                . ' free to combine as many nested operators as you want. You'
                . ' might also want to skip this, define it later and/or just'
                . ' add this set of services to single hosts'
            )
        ]);

        return $this;
    }
}
