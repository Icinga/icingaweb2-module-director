<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Web\Form\DirectorObjectForm;
use Icinga\Module\Director\Objects\IcingaService;

class IcingaServiceDictionaryMemberForm extends DirectorObjectForm
{
    /** @var IcingaService */
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
            'required'    => !$this->object()->isApplyRule(),
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
        return IcingaService::class;
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
