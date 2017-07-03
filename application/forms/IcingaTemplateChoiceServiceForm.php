<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaTemplateChoiceService;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

// TODO: combine with the one for hosts
class IcingaTemplateChoiceServiceForm extends DirectorObjectForm
{
    public function setup()
    {
        /** @var IcingaTemplateChoiceService $object */
        $object = $this->object();

        $this->addElement('text', 'object_name', array(
            'label'       => $this->translate('Choice name'),
            'required'    => true,
            'description' => $this->translate(
                'This will be shown as a label for the given choice'
            )
        ));

        $this->addElement('textarea', 'description', array(
            'label'       => $this->translate('Description'),
            'rows' => 4,
            'description' => $this->translate(
                'A detailled description explaining what this choice is all about'
            )
        ));

        $this->addElement('extensibleSet', 'members', array(
            'label'       => $this->translate('Available choices'),
            'required'    => true,
            'ignore'      => true,
            'description' => $this->translate(
                'Your users will be allowed to choose among those templates'
            ),
            'value' => $object->getChoices(),
            'multiOptions' => $this->fetchUnboundTemplates()
        ));

        $this->setButtons();
    }

    protected function fetchUnboundTemplates()
    {
        $db = $this->getDb()->getDbAdapter();
        $query = $db->select()->from(
            ['o' => 'icinga_service'],
            [
                'k' => 'o.object_name',
                'v' => 'o.object_name',
            ]
        )->where("o.object_type = 'template'");

        return $db->fetchPairs($query);
    }
}
