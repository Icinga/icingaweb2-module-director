<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;

class IcingaAddServiceToMultipleHostsForm extends DirectorObjectForm
{
    /** @var IcingaHost[] */
    private $hosts;

    /** @var IcingaService */
    protected $object;

    public function setup()
    {
        if ($this->object === null) {
            $this->object = IcingaService::create(
                array('object_type' => 'object'),
                $this->db
            );
        }

        $this->addSingleImportElement();

        if (! ($imports = $this->getSentOrObjectValue('imports'))) {
            $this->setButtons();
            return;
        }

        $this->addNameElement()
             ->groupMainProperties()
             ->setButtons();
    }
    /**
     * @return $this
     */
    protected function groupMainProperties()
    {
        $elements = array(
            'object_type',
            'imports',
            'object_name',
        );

        $this->addDisplayGroup($elements, 'object_definition', array(
            'decorators' => array(
                'FormElements',
                array('HtmlTag', array('tag' => 'dl')),
                'Fieldset',
            ),
            'order' => 20,
            'legend' => $this->translate('Main properties')
        ));

        return $this;
    }

    /**
     * @param bool $required
     * @return $this
     */
    protected function addSingleImportElement($required = null)
    {
        $enum = $this->enumServiceTemplates();
        if (empty($enum)) {
            if ($required) {
                if ($this->hasBeenSent()) {
                    $this->addError($this->translate('No service has been chosen'));
                } else {
                    if ($this->hasPermission('director/admin')) {
                        $html = $this->translate('Please define a Service Template first');
                    } else {
                        $html = $this->translate('No Service Templates have been provided yet');
                    }
                    $this->addHtml('<p class="warning">' . $html . '</p>');
                }
            }

            return $this;
        }

        $this->addElement('select', 'imports', array(
            'label'        => $this->translate('Service'),
            'description'  => $this->translate(
                'Choose a service template'
            ),
            'required'     => true,
            'multiOptions' => $this->optionallyAddFromEnum($enum),
            // TODO -> 'value'        => $this->presetImports,
            'class'        => 'autosubmit'
        ));

        return $this;
    }


    protected function enumServiceTemplates()
    {
        $tpl = $this->getDb()->enumIcingaTemplates('service');
        if (empty($tpl)) {
            return array();
        }

        $tpl = array_combine($tpl, $tpl);
        return $tpl;
    }

    protected function setupHostRelatedElements()
    {
        $this->addHidden('host_id', $this->host->id);
        $this->addHidden('object_type', 'object');
        $this->addImportsElement();
        $imports = $this->getSentOrObjectValue('imports');

        if ($this->hasBeenSent()) {
            $imports = $this->getElement('imports')->setValue($imports)->getValue();
        }

        if ($this->isNew() && empty($imports)) {
            $this->groupMainProperties();
            return;
        }

        if ($this->hasBeenSent()) {
            $name = $this->getSentOrObjectValue('object_name');
            if (!strlen($name)) {
                $this->setElementValue('object_name', end($imports));
                $this->object->object_name = end($imports);
            }
        }
    }

    public function setHosts(array $hosts)
    {
        $this->hosts = $hosts;
        return $this;
    }

    protected function addNameElement()
    {
        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Name'),
            'required'    => true,
            'description' => $this->translate(
                'Name for the Icinga service you are going to create'
            )
        ));

        return $this;
    }

    public function onSuccess()
    {
        $vars = array();
        foreach ($this->object->vars() as $key => $var) {
            $vars[$key] = $var->getValue();
        }

        $o = $this->object;
        foreach ($this->hosts as $host) {
            $service = IcingaService::fromPlainObject(
                $o->toPlainObject(),
                $o->getConnection()
            )->set('host_id', $host->get('id'));

            $service->store();
        }

        $this->redirectOnSuccess('Gogogo');
    }
}
