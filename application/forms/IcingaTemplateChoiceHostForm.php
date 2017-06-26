<?php

namespace Icinga\Module\Director\Forms;

use Icinga\Module\Director\Objects\IcingaTemplateChoiceHost;
use Icinga\Module\Director\Web\Form\DirectorObjectForm;

class IcingaTemplateChoiceHostForm extends DirectorObjectForm
{
    public function setup()
    {
        /** @var IcingaTemplateChoiceHost $object */
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
            ['h' => 'icinga_host'],
            [
                'k' => 'h.object_name',
                'v' => 'h.object_name',
            ]
        )->where("h.object_type = 'template'");
//            ->where('')
        return $db->fetchPairs($query);
    }
}
