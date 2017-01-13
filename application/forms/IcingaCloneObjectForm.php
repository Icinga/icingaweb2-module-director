<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaObject;
use Icinga\Module\Director\Web\Form\QuickForm;

class IcingaCloneObjectForm extends QuickForm
{
    /** @var IcingaObject */
    protected $object;

    public function setup()
    {
        $this->addElement('text', 'new_object_name', array(
            'label'    => $this->translate('New name'),
            'required' => true,
            'value'    => $this->object->getObjectName(),
        ));

        $this->addElement('select', 'clone_type', array(
            'label'    => 'Clone type',
            'required' => true,
            'multiOptions' => array(
                'equal' => $this->translate('Clone the object as is, preserving imports'),
                'flat'  => $this->translate('Flatten all inherited properties, strip imports'),
            )
        ));

        $this->submitLabel = sprintf(
            $this->translate('Clone "%s"'),
            $this->object->getObjectName()
        );
    }

    public function onSuccess()
    {

        $object = $this->object;
        $newname = $this->getValue('new_object_name');
        $resolve = $this->getValue('clone_type') === 'flat';

        $msg = sprintf(
            'The %s "%s" has been cloned from "%s"',
            $object->getShortTableName(),
            $newname,
            $object->getObjectName()
        );

        $new = $object::fromPlainObject(
            $object->toPlainObject($resolve),
            $object->getConnection()
        )->set('object_name', $newname);

        if ($new->store()) {
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
