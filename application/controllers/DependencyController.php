<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Objects\IcingaDependency;

class DependencyController extends ObjectController
{
    protected $apply;

    protected function beforeTabs()
    {
    }

    public function init()
    {
        parent::init();

        if ($apply = $this->params->get('apply')) {
            $this->apply = IcingaDependency::load(
                array('object_name' => $apply, 'object_type' => 'template'),
                $this->db()
            );
        }
    }

    protected function loadObject()
    {
        if ($this->object === null) {
            if ($name = $this->params->get('name')) {
                $params = array('object_name' => $name);
                $db = $this->db();

                $this->object = IcingaDependency::load($params, $db);
            } else {
                parent::loadObject();
            }
        }

        return $this->object;
    }

    public function loadForm($name)
    {
        $form = parent::loadForm($name);
        return $form;
    }

    protected function beforeHandlingAddRequest($form)
    {
        if ($this->apply) {
            $form->createApplyRuleFor($this->apply);
        }
    }
}
