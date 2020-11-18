<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Exception\NotFoundError;
use Icinga\Module\Director\Db;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Form\DirectorForm;
use Icinga\Security\SecurityException;

class IcingaHostSelfServiceForm extends DirectorForm
{
    /** @var string */
    private $hostApiKey;

    /** @var IcingaHost */
    private $template;

    private $hostName;

    public function setup()
    {
        if ($this->hostName === null) {
            $this->addElement('text', 'object_name', array(
                'label'    => $this->translate('Host name'),
                'required' => true,
                'value'    => $this->hostName,
            ));
        }
        $this->addElement('text', 'display_name', array(
            'label'    => $this->translate('Alias'),
        ));

        $this->addElement('text', 'address', array(
            'label' => $this->translate('Host address'),
            'description' => $this->translate(
                'Host address. Usually an IPv4 address, but may be any kind of address'
                . ' your check plugin is able to deal with'
            )
        ));

        $this->addElement('text', 'address6', array(
            'label' => $this->translate('IPv6 address'),
            'description' => $this->translate('Usually your hosts main IPv6 address')
        ));

        if ($this->template === null) {
            $this->addElement('text', 'key', array(
                'label'    => $this->translate('API Key'),
                'ignore'   => true,
                'required' => true,
            ));
        }

        $this->submitLabel = sprintf(
            $this->translate('Register')
        );
    }

    public function setHostName($name)
    {
        $this->hostName = $name;
        $this->removeElement('object_name');
        return $this;
    }

    public function loadTemplateWithApiKey($key)
    {
        $this->template = IcingaHost::loadWithApiKey($key, $this->getDb());
        if (! $this->template->isTemplate()) {
            throw new NotFoundError('Got invalid API key "%s"', $key);
        }

        if ($this->template->getResolvedProperty('has_agent') !== 'y') {
            throw new NotFoundError(
                'Got valid API key "%s", but template is not for Agents',
                $key
            );
        }

        $this->removeElement('key');

        return $this->template;
    }

    public function listMissingRequiredFields()
    {
        $result = [];
        foreach ($this->getElements() as $element) {
            if (in_array('isEmpty', $element->getErrors())) {
                $result[] = $element->getName();
            }
        }

        return $result;
    }

    public function isMissingRequiredFields()
    {
        return count($this->listMissingRequiredFields()) > 0;
    }

    public function onSuccess()
    {
        $db = $this->getDb();
        if ($this->template === null) {
            $this->loadTemplateWithApiKey($this->getValue('key'));
        }
        $name = $this->hostName ?: $this->getValue('object_name');
        if (IcingaHost::exists($name, $db)) {
            $host = IcingaHost::load($name, $db);
            if ($host->isTemplate()) {
                throw new SecurityException(
                    'You are not allowed to create "%s"',
                    $name
                );
            }

            if (null !== $host->getProperty('api_key')) {
                throw new SecurityException(
                    'The host "%s" has already been registered',
                    $name
                );
            }

            $propertyNames = ['display_name', 'address', 'address6'];
            foreach ($propertyNames as $property) {
                if (\strlen($value = $this->getValue($property)) > 0) {
                    $host->set($property, $value);
                }
            }
        } else {
            $host = IcingaHost::create(array_filter($this->getValues(), 'strlen'), $db);
            $host->set('object_name', $name);
            $host->set('object_type', 'object');
            $host->set('imports', [$this->template]);
        }

        $key = $host->generateApiKey();
        $host->store($db);
        $this->hostApiKey = $key;
    }

    /**
     * @return string|null
     */
    public function getHostApiKey()
    {
        return $this->hostApiKey;
    }

    public static function create(Db $db)
    {
        return static::load()->setDb($db);
    }
}
