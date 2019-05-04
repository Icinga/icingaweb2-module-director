<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaAddServiceSetForm extends DirectorObjectForm
{
    /** @var IcingaHost[] */
    private $hosts;

    /** @var IcingaHost */
    private $host;

    /** @var IcingaServiceSet */
    protected $object;

    protected $objectName = 'service_set';

    protected $listUrl = 'director/services/sets';

    public function setup()
    {
        if ($this->object === null) {
            $this->object = IcingaServiceSet::create(
                ['object_type' => 'object'],
                $this->db
            );
        }

        $object = $this->object();
        if ($this->hasBeenSent()) {
            $object->set('object_name', $this->getSentValue('imports'));
            $object->set('imports', $object->getObjectName());
        }

        if (! $object->hasBeenLoadedFromDb()) {
            $this->addSingleImportsElement();
        }

        if (count($object->get('imports'))) {
            $description = $object->getResolvedProperty('description');
            if ($description) {
                $this->addHtmlHint($description);
            }
        }

        $this->addHidden('object_type', 'object');
        $this->setButtons();
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

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
        return $this;
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

    protected function addSingleImportsElement()
    {
        $enum = $this->enumAllowedTemplates();

        $this->addElement('select', 'imports', array(
            'label'        => $this->translate('Service set'),
            'description'  => $this->translate(
                'The service Set that should be assigned'
            ),
            'required'     => true,
            'multiOptions' => $this->optionallyAddFromEnum($enum),
            'class'        => 'autosubmit'
        ));

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
            IcingaServiceSet::fromPlainObject($plain, $db)
                ->set('host_id', $host->get('id'))
                ->store();
        }

        $msg = sprintf(
            $this->translate('The Service Set "%s" has been added to %d hosts'),
            $this->object->getObjectName(),
            count($this->hosts)
        );

        $this->redirectOnSuccess($msg);
    }
}
