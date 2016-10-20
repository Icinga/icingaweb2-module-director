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
        $this->addImportsElement();

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Service set name'),
            'description' => $this->translate(
                'A short name identifying this set of services'
            ),
            'required'    => true,
        ));
        
        $this->addElement('textarea', 'description', array(
            'label'       => $this->translate('Description'),
            'description' => $this->translate(
                'A meaningful description explaining your users what to expect'
                . ' when assigning this set of services'
            ),
            'rows'        => '3',
            'required'    => ! $this->isTemplate(),
        ));


        if ($this->host === null) {
            $this->addHidden('object_type', 'object');

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
        } else {
            $this->addHidden('object_type', 'object');
            $this->addHidden('host_id', $this->host->id);
        }

        $services = array();
        foreach ($this->getSentOrObjectValue('service') as $name) {
            $services[] = IcingaService::load(array(
                'object_name' => $name,
                'object_type' => 'template'
            ), $this->db);
        }

        if ($this->assertResolvedImports()) {
            $loader = $this->fieldLoader($this->object);
            $loader->loadFieldsForMultipleObjects($services);
        }

        $this->setButtons();
    }

    public function setHost(IcingaHost $host)
    {
        $this->host = $host;
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
