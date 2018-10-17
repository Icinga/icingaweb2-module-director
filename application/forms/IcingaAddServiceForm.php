<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaService;

class IcingaAddServiceForm extends DirectorObjectForm
{
    /** @var IcingaHost[] */
    private $hosts;

    /** @var IcingaHost */
    private $host;

    /** @var IcingaService */
    protected $object;

    protected $objectName = 'service';

    public function setup()
    {
        if ($this->object === null) {
            $this->object = IcingaService::create(
                ['object_type' => 'object'],
                $this->db
            );
        }

        $this->addSingleImportElement();

        if (! ($imports = $this->getSentOrObjectValue('imports'))) {
            $this->setSubmitLabel($this->translate('Next'));
            $this->groupMainProperties();
            return;
        }

        $this->removeElement('imports');
        $this->addHidden('imports', $imports);
        $this->setElementValue('imports', $imports);
        $this->addNameElement();
        $name = $this->getSentOrObjectValue('object_name');
        if (empty($name)) {
            $this->setElementValue('object_name', $imports);
        }
        $this->groupMainProperties()
             ->setButtons();
    }

    protected function groupMainProperties($importsFirst = false)
    {
        $elements = [
            'object_type',
            'imports',
            'object_name',
        ];

        $this->addDisplayGroup($elements, 'object_definition', [
            'decorators' => [
                'FormElements',
                ['HtmlTag', ['tag' => 'dl']],
                'Fieldset',
            ],
            'order'  => 20,
            'legend' => $this->translate('Main properties')
        ]);

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
                        $html = sprintf(
                            $this->translate('Please define a %s first'),
                            Link::create(
                                $this->translate('Service Template'),
                                'director/service/add',
                                ['type' => 'template']
                            )
                        );
                    } else {
                        $html = $this->translate('No Service Templates have been provided yet');
                    }
                    $this->addHtml('<p class="warning">' . $html . '</p>');
                }
            }

            return $this;
        }

        $this->addElement('select', 'imports', [
            'label'        => $this->translate('Service'),
            'description'  => $this->translate(
                'Choose a service template'
            ),
            'required'     => true,
            'multiOptions' => $this->optionalEnum($enum),
            'class'        => 'autosubmit'
        ]);

        return $this;
    }

    protected function enumServiceTemplates()
    {
        $tpl = $this->getDb()->enumIcingaTemplates('service');
        return array_combine($tpl, $tpl);
    }

    /**
     * @param IcingaHost[] $hosts
     * @return $this
     */
    public function setHosts(array $hosts)
    {
        $this->hosts = $hosts;
        return $this;
    }

    /**
     * @param IcingaHost $host
     * @return $this
     */
    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
    }

    protected function addNameElement()
    {
        $this->addElement('text', 'object_name', [
            'label'       => $this->translate('Name'),
            'required'    => true,
            'description' => $this->translate(
                'Name for the Icinga service you are going to create'
            )
        ]);

        return $this;
    }

    public function onSuccess()
    {
        if ($this->host !== null) {
            $this->object->set('host_id', $this->host->get('id'));
            parent::onSuccess();
            return;
        }

        $plain = $this->object->toPlainObject();
        $db = $this->object->getConnection();

        foreach ($this->hosts as $host) {
            IcingaService::fromPlainObject($plain, $db)
                ->set('host_id', $host->get('id'))
                ->store();
        }

        $msg = sprintf(
            $this->translate('The service "%s" has been added to %d hosts'),
            $this->object->getObjectName(),
            count($this->hosts)
        );

        $this->redirectOnSuccess($msg);
    }
}
