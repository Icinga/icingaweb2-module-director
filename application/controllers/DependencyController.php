<?php

namespace Icinga\Module\Director\Controllers;

use Icinga\Module\Director\Forms\IcingaDependencyForm;
use Icinga\Module\Director\Web\Controller\ObjectController;
use Icinga\Module\Director\Objects\IcingaDependency;

class DependencyController extends ObjectController
{
    protected $apply;

    /**
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\NotFoundError
     */
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

    /**
     * @return \Icinga\Module\Director\Objects\IcingaObject
     * @throws \Icinga\Exception\ConfigurationError
     * @throws \Icinga\Exception\InvalidPropertyException
     * @throws \Icinga\Exception\NotFoundError
     */
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

    /**
     * Hint: this is never being called. Why?
     *
     * @param $form
     */
    protected function beforeHandlingAddRequest($form)
    {
        /** @var IcingaDependencyForm $form */
        if ($this->apply) {
            $form->createApplyRuleFor($this->apply);
        }
    }
}
