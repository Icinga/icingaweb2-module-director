<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Exception\IcingaException;
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

    protected $baseObjectUrl;

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
                'label'        => 'Clone type',
                'required'     => true,
                'multiOptions' => array(
                    'equal' => $this->translate('Clone the object as is, preserving imports'),
                    'flat'  => $this->translate('Flatten all inherited properties, strip imports'),
                )
            ));
        }

        if ($this->object instanceof IcingaHost
            || $this->object instanceof IcingaServiceSet
        ) {
            $this->addBoolean('clone_services', [
                'label'       => $this->translate('Clone Services'),
                'description' => $this->translate(
                    'Also clone single Services defined for this Host'
                )
            ], 'y');
        }

        if ($this->object instanceof IcingaHost) {
            $this->addBoolean('clone_service_sets', [
                'label'       => $this->translate('Clone Service Sets'),
                'description' => $this->translate(
                    'Also clone single Service Sets defined for this Host'
                )
            ], 'y');
        }

        if ($this->object instanceof IcingaService) {
            if ($this->object->get('service_set_id') !== null) {
                $this->addElement('select', 'target_service_set', [
                    'label'        => $this->translate('Target Service Set'),
                    'description'  => $this->translate(
                        'Clone this service to the very same or to another Service Set'
                    ),
                    'multiOptions' => $this->enumServiceSets(),
                    'value'        => $this->object->get('service_set_id')
                ]);
            } elseif ($this->object->get('host_id') !== null) {
                $this->addElement('text', 'target_host', [
                    'label'                   => $this->translate('Target Host'),
                    'description'             => $this->translate(
                        'Clone this service to the very same or to another Host'
                    ),
                    'value'                   => $this->object->get('host'),
                    'class'                   => "autosubmit director-suggest",
                    'data-suggestion-context' => 'HostsAndTemplates',
                ]);
            }
        }

        if ($this->object->isTemplate() && $this->object->supportsFields()) {
            $this->addBoolean('clone_fields', [
                'label'       => $this->translate('Clone Template Fields'),
                'description' => $this->translate(
                    'Also clone fields provided by this Template'
                )
            ], 'y');
        }

        $this->submitLabel = sprintf(
            $this->translate('Clone "%s"'),
            $name
        );
    }

    public function setObjectBaseUrl($url)
    {
        $this->baseObjectUrl = $url;

        return $this;
    }

    public function onSuccess()
    {
        $object = $this->object;
        $table = $object->getTableName();
        $type = $object->getShortTableName();
        $connection = $object->getConnection();
        $db = $connection->getDbAdapter();
        $newName = $this->getValue('new_object_name');
        $resolve = Acl::instance()->hasPermission('director/admin')
            && $this->getValue('clone_type') === 'flat';

        $msg = sprintf(
            'The %s "%s" has been cloned from "%s"',
            $type,
            $newName,
            $object->getObjectName()
        );

        if ($object->isTemplate() && $object->getObjectName() === $newName) {
            throw new IcingaException(
                $this->translate('Name needs to be changed when cloning a Template')
            );
        }

        $new = $object::fromPlainObject(
            $object->toPlainObject($resolve),
            $connection
        )->set('object_name', $newName);

        if ($new->isExternal()) {
            $new->set('object_type', 'object');
        }

        if ($set = $this->getValue('target_service_set')) {
            $new->set(
                'service_set_id',
                IcingaServiceSet::loadWithAutoIncId((int) $set, $connection)->get('id')
            );
        } elseif ($host = $this->getValue('target_host')) {
            $new->set('host', $host);
        }

        $services = [];
        $sets = [];
        if ($object instanceof IcingaHost) {
            $new->set('api_key', null);
            if ($this->getValue('clone_services') === 'y') {
                $services = $object->fetchServices();
            }
            if ($this->getValue('clone_service_sets') === 'y') {
                $sets = $object->fetchServiceSets();
            }
        } elseif ($object instanceof IcingaServiceSet) {
            if ($this->getValue('clone_services') === 'y') {
                $services = $object->fetchServices();
            }
        }
        if ($this->getValue('clone_fields') === 'y') {
            $fields = $db->fetchAll(
                $db->select()
                    ->from($table . '_field')
                    ->where("${type}_id = ?", $object->get('id'))
            );
        } else {
            $fields = [];
        }

        if ($new->store()) {
            $newId = $new->get('id');
            foreach ($services as $service) {
                $clone = IcingaService::fromPlainObject(
                    $service->toPlainObject(),
                    $connection
                );

                if ($new instanceof IcingaHost) {
                    $clone->set('host_id', $newId);
                } elseif ($new instanceof IcingaServiceSet) {
                    $clone->set('service_set_id', $newId);
                }
                $clone->store();
            }

            foreach ($sets as $set) {
                IcingaServiceSet::fromPlainObject(
                    $set->toPlainObject(),
                    $connection
                )->set('host_id', $newId)->store();
            }

            foreach ($fields as $row) {
                $row->{"${type}_id"} = $newId;
                $db->insert($table . '_field', (array) $row);
            }

            if ($new instanceof IcingaServiceSet) {
                $this->setSuccessUrl(
                    'director/serviceset',
                    $new->getUrlParams()
                );
            } else {
                $this->setSuccessUrl(
                    $this->baseObjectUrl ?: 'director/' . strtolower($type),
                    $new->getUrlParams()
                );
            }

            $this->redirectOnSuccess($msg);
        }
    }

    protected function enumServiceSets()
    {
        $db = $this->object->getConnection()->getDbAdapter();
        return $db->fetchPairs(
            $db->select()
                ->from('icinga_service_set', ['id', 'object_name'])
                ->order('object_name')
        );
    }

    public function setObject(IcingaObject $object)
    {
        $this->object = $object;
        return $this;
    }
}
