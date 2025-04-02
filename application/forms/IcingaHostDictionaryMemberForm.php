<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaHost;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaHostDictionaryMemberForm extends DirectorObjectForm
{
    /** @var IcingaHost */
    protected $object;

    private $succeeded;

    /**
     * @throws \Zend_Form_Exception
     */
    public function setup()
    {
        $this->addHidden('object_type', 'object');
        $this->addElement('text', 'object_name', [
            'label'       => $this->translate('Name'),
            'description' => $this->translate(
                'Name for the instance you are going to create'
            )
        ]);
        $this->groupMainProperties()->setButtons();
    }

    protected function isNew()
    {
        return $this->object === null;
    }

    protected function deleteObject($object)
    {
    }

    protected function getObjectClassname()
    {
        return IcingaHost::class;
    }

    public function succeeded()
    {
        return $this->succeeded;
    }

    public function onSuccess()
    {
        $this->succeeded = true;
    }
}
