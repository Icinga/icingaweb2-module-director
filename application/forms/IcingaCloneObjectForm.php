<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Acl;
use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Objects\IcingaService;
use Icinga\Module\Director\Objects\IcingaServiceSet;
use Icinga\Module\Director\Web\Form\DirectorForm;

class IcingaCloneObjectForm extends DirectorForm
{
    /** @var IcingaObject */
    protected $object;

    public function setup()
    {
        $name = $this->object->getObjectName();
        $this->addElement('text', 'new_object_name', array(
            'label'    => $this->translate('New name'),
            'required' => true,
            'value'    => $name,
        ));

        if (Acl::instance()->hasPermission('director/admin')) {
            $this->addElement('select', 'clone_type', array(
                'label'    => 'Clone type',
                'required' => true,
                'multiOptions' => array(
                    'equal' => $this->translate('Clone the object as is, preserving imports'),
                    'flat'  => $this->translate('Flatten all inherited properties, strip imports'),
                )
            ));
        }

        if ($this->object instanceof IcingaHost) {
            $this->addBoolean('clone_services', [
                'label'       => $this->translate('Clone Services'),
                'description' => $this->translate(
                    'Also clone single Services defined for this Host'
                )
            ], 'y');
            $this->addBoolean('clone_service_sets', [
                'label'       => $this->translate('Clone Service Sets'),
                'description' => $this->translate(
                    'Also clone single Service Sets defined for this Host'
                )
            ], 'y');
        }

        $this->submitLabel = sprintf(
            $this->translate('Clone "%s"'),
            $name
        );
    }

    public function onSuccess()
    {
        $object = $this->object;
        $connection = $object->getConnection();
        $newname = $this->getValue('new_object_name');
        $resolve = Acl::instance()->hasPermission('director/admin')
            && $this->getValue('clone_type') === 'flat';

        $msg = sprintf(
            'The %s "%s" has been cloned from "%s"',
            $object->getShortTableName(),
            $newname,
            $object->getObjectName()
        );

        $new = $object::fromPlainObject(
            $object->toPlainObject($resolve),
            $connection
        )->set('object_name', $newname);

        if ($new->isExternal()) {
            $new->set('object_type', 'object');
        }

        if ($object instanceof IcingaHost) {
            $new->set('api_key', null);
            if ($this->getValue('clone_services') === 'y') {
                $services = $object->fetchServices();
            } else {
                $services = [];
            }
            if ($this->getValue('clone_service_sets') === 'y') {
                $sets = $object->fetchServiceSets();
            } else {
                $sets = [];
            }
        } else {
            $services = [];
            $sets = [];
        }

        if ($new->store()) {
            foreach ($services as $service) {
                IcingaService::fromPlainObject(
                    $service->toPlainObject(),
                    $connection
                )->set('host_id', $new->get('id'))->store();
            }
            foreach ($sets as $set) {
                IcingaServiceSet::fromPlainObject(
                    $set->toPlainObject(),
                    $connection
                )->set('host_id', $new->get('id'))->store();
            }
            $this->setSuccessUrl(
                'director/' . strtolower($object->getShortTableName()),
                $new->getUrlParams()
            );

            $this->redirectOnSuccess($msg);
        }
    }

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        return $this;
    }
}
