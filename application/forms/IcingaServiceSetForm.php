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
        $this->addDescriptionElement();

        $this->addElement('multiselect', 'service', array(
            'label'        => $this->translate('Services'),
            'description'  => $this->translate(
                'Services in this set'
            ),
            'rows'         => '5',
            'multiOptions' => $this->enumServices(),
            'required'     => true,
            'class'        => 'autosubmit',
        ));
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

    protected function enumServices()
    {
        $db = $this->db->getDbAdapter();
        $query = $db->select()
            ->from('icinga_service', 'object_name')
            ->where('object_type = ?', 'template')
            ->order('object_name');
        $names = $db->fetchCol($query);

        return array_combine($names, $names);
    }
}
