<?php

namespace Icinga\Module\Director\Forms;

use gipfl\IcingaWeb2\Link;
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

        $this->addSingleImportElement(true);

        if (empty($this->enumServiceTemplates())) {
            $this->setSubmitLabel(false);

            return;
        }

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
            'order'  => self::GROUP_ORDER_OBJECT_DEFINITION,
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
        $this->addElement('text', 'imports', [
            'label'        => $this->translate('Service'),
            'description'  => $this->translate('Choose a service template'),
            'required'     => true,
            'data-suggestion-context' => 'servicetemplates',
            'class'        => 'autosubmit director-suggest'
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
            if ($id = $this->host->get('id')) {
                $this->object->set('host_id', $id);
            } else {
                $this->object->set('host', $this->host->getObjectName());
            }
            parent::onSuccess();
            return;
        }

        $plain = $this->object->toPlainObject();
        $db = $this->object->getConnection();

        // TODO: Test this:
        foreach ($this->hosts as $host) {
            $service = IcingaService::fromPlainObject($plain, $db)
                ->set('host_id', $host->get('id'));
            $this->getDbObjectStore()->store($service);
        }

        $msg = sprintf(
            $this->translate('The service "%s" has been added to %d hosts'),
            $this->object->getObjectName(),
            count($this->hosts)
        );

        $this->redirectOnSuccess($msg);
    }
}
